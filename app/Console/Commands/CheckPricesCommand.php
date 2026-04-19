<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\PriceCheckerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * make app-bash
 * php artisan prices:check
 */
#[Signature('prices:check')]
#[Description('Check OLX listing prices for all actively subscribed listings')]
class CheckPricesCommand extends Command
{
    public function handle(PriceCheckerService $priceCheckerService): int
    {
        $listings = Listing::active()->whereHas(
            'subscriptions',
            static fn ($query) => $query->whereNotNull('verified_at'),
        )->get();

        if ($listings->isEmpty()) {
            $this->info('No active subscriptions found.');

            return self::SUCCESS;
        }

        $this->info("Checking prices for {$listings->count()} listing(s)...");

        $listings->each(static function (Listing $listing) use ($priceCheckerService): void {
            $priceCheckerService->check($listing);
        });

        $this->info('Done.');

        return self::SUCCESS;
    }
}
