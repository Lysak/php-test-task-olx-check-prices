<?php

namespace App\Listeners;

use App\Events\PriceChanged;
use App\Jobs\SendPriceChangeMail;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendPriceChangeNotifications implements ShouldQueue
{
    private const int BATCH_SIZE = 40;

    public function handle(PriceChanged $event): void
    {
        $event->listing
            ->activeSubscriptions()
            ->chunkById(self::BATCH_SIZE, static function ($subscriptions) use ($event): void {
                $subscriptions->each(static function (Subscription $subscription) use ($event): void {
                    dispatch(new SendPriceChangeMail(
                        $subscription,
                        $event->listing,
                        $event->oldPrice,
                        $event->newPrice,
                    ));
                });
            });
    }
}
