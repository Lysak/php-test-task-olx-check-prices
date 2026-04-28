<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\PriceCheckerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * make app-bash
 * php artisan prices:check
 */
#[Signature('prices:check')]
#[Description('Check OLX listing prices for all actively subscribed listings')]
class CheckPricesCommand extends Command
{
    private const int BATCH_SIZE = 40;

    public function handle(PriceCheckerService $priceCheckerService): int
    {
        $this->info('Checking prices for active listings...');

        $found = false;

        Listing::active()
            ->whereHas('subscriptions', static fn ($query) => $query->whereNotNull('verified_at'))
            ->chunkById(self::BATCH_SIZE, static function (Collection $listings) use ($priceCheckerService, &$found): void {
                $found = true;
                $listings->each(static fn (Listing $listing) => $priceCheckerService->check($listing));
            });

        $this->info($found ? 'Done.' : 'No active listings found.');

        return self::SUCCESS;
    }
}
