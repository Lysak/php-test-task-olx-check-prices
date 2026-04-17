<?php

namespace App\Providers;

use App\Events\ListingUnavailable;
use App\Events\PriceChanged;
use App\Listeners\SendListingUnavailableNotifications;
use App\Listeners\SendPriceChangeNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        PriceChanged::class => [
            SendPriceChangeNotifications::class,
        ],
        ListingUnavailable::class => [
            SendListingUnavailableNotifications::class,
        ],
    ];
}
