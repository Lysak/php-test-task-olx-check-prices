<?php

namespace Tests\Feature;

use App\Events\PriceChanged;
use App\Jobs\SendPriceChangeMail;
use App\Listeners\SendPriceChangeNotifications;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SendPriceChangeNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dispatches_one_job_for_single_verified_subscriber(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        Bus::fake();

        (new SendPriceChangeNotifications)->handle(new PriceChanged($listing, 1000.0, 1200.0));

        Bus::assertDispatched(SendPriceChangeMail::class, 1);
    }

    public function test_dispatches_one_job_per_verified_subscriber(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->verified()->count(3)->create(['listing_id' => $listing->id]);

        Bus::fake();

        (new SendPriceChangeNotifications)->handle(new PriceChanged($listing, 1000.0, 1200.0));

        Bus::assertDispatched(SendPriceChangeMail::class, 3);
    }

    public function test_does_not_dispatch_job_for_unverified_subscribers(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->count(2)->create(['listing_id' => $listing->id]);

        Bus::fake();

        (new SendPriceChangeNotifications)->handle(new PriceChanged($listing, 1000.0, 1200.0));

        Bus::assertNothingDispatched();
    }
}
