<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PriceScraperInterface;
use App\Events\ListingUnavailable;
use App\Events\PriceChanged;
use App\Models\Listing;
use Psr\Log\LoggerInterface;

final readonly class PriceCheckerService
{
    public function __construct(
        private PriceScraperInterface $scraper,
        private LoggerInterface $logger,
    ) {}

    public function check(Listing $listing): void
    {
        $newPrice = $this->scraper->fetchPrice($listing->url);

        if ($newPrice === null) {
            $this->handleFailure($listing);

            return;
        }

        if ($listing->consecutive_failures > 0) {
            $listing->update(['consecutive_failures' => 0]);
        }

        if ($listing->current_price === null) {
            $listing->update(['current_price' => $newPrice, 'last_checked_at' => now()]);
            $listing->priceHistories()->create(['price' => $newPrice, 'recorded_at' => now()]);

            event(new PriceChanged($listing, null, $newPrice));

            return;
        }

        $oldPrice = (float) $listing->current_price;

        if ($oldPrice === $newPrice) {
            return;
        }

        $listing->priceHistories()->create([
            'price' => $newPrice,
            'recorded_at' => now(),
        ]);

        $listing->update([
            'current_price' => $newPrice,
            'last_checked_at' => now(),
        ]);

        $this->logger->info('Price changed', [
            'listing_id' => $listing->id,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
        ]);

        event(new PriceChanged($listing, $oldPrice, $newPrice));
    }

    private function handleFailure(Listing $listing): void
    {
        $failures = $listing->consecutive_failures + 1;

        $listing->update(['consecutive_failures' => $failures]);

        $this->logger->warning('Price check failed', [
            'listing_id' => $listing->id,
            'consecutive_failures' => $failures,
        ]);

        $threshold = (int) config('listing.failure_threshold', 3);

        if ($failures >= $threshold) {
            $listing->update(['deactivated_at' => now()]);

            $this->logger->error('Listing deactivated after consecutive failures', [
                'listing_id' => $listing->id,
            ]);

            event(new ListingUnavailable($listing));
        }
    }
}
