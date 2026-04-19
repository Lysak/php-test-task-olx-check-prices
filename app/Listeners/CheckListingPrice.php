<?php

namespace App\Listeners;

use App\Events\SubscriptionVerified;
use App\Models\Listing;
use App\Services\PriceCheckerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

final readonly class CheckListingPrice implements ShouldQueue
{
    public function __construct(private PriceCheckerService $priceCheckerService) {}

    public function handle(SubscriptionVerified $event): void
    {
        $listing = $event->subscription->listing;

        if ($this->shouldCheck($listing)) {
            $this->priceCheckerService->check($listing);
        }
    }

    private function shouldCheck(Listing $listing): bool
    {
        if ($listing->deactivated_at !== null) {
            return false;
        }

        if ($listing->current_price === null || $listing->last_checked_at === null) {
            return true;
        }

        return $listing->last_checked_at->lt(Carbon::now()->subHour());
    }
}
