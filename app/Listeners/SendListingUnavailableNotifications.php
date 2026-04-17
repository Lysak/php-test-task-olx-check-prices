<?php

namespace App\Listeners;

use App\Events\ListingUnavailable;
use App\Jobs\SendListingUnavailableMail;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendListingUnavailableNotifications implements ShouldQueue
{
    private const int BATCH_SIZE = 40;

    public function handle(ListingUnavailable $event): void
    {
        $event->listing
            ->activeSubscriptions()
            ->chunkById(self::BATCH_SIZE, static function ($subscriptions) use ($event): void {
                $subscriptions->each(static function (Subscription $subscription) use ($event): void {
                    dispatch(new SendListingUnavailableMail($subscription, $event->listing));
                });
            });

        $event->listing->activeSubscriptions()->delete();
    }
}
