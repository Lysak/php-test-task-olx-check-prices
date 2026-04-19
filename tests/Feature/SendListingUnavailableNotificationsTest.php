<?php

namespace Tests\Feature;

use App\Events\ListingUnavailable;
use App\Jobs\SendListingUnavailableMail;
use App\Listeners\SendListingUnavailableNotifications;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SendListingUnavailableNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dispatches_job_for_each_verified_subscriber(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->verified()->count(2)->create(['listing_id' => $listing->id]);

        Bus::fake();

        (new SendListingUnavailableNotifications)->handle(new ListingUnavailable($listing));

        Bus::assertDispatched(SendListingUnavailableMail::class, 2);
    }

    public function test_deletes_active_subscriptions_after_dispatching_jobs(): void
    {
        $listing = Listing::factory()->create();
        $verified = Subscription::factory()->verified()->count(2)->create(['listing_id' => $listing->id]);

        Bus::fake();

        (new SendListingUnavailableNotifications)->handle(new ListingUnavailable($listing));

        foreach ($verified as $subscription) {
            $this->assertSoftDeleted($subscription);
        }
    }

    public function test_does_not_dispatch_or_delete_unverified_subscriptions(): void
    {
        $listing = Listing::factory()->create();
        $unverified = Subscription::factory()->count(2)->create(['listing_id' => $listing->id]);

        Bus::fake();

        (new SendListingUnavailableNotifications)->handle(new ListingUnavailable($listing));

        Bus::assertNothingDispatched();
        foreach ($unverified as $subscription) {
            $this->assertModelExists($subscription);
        }
    }
}
