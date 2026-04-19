<?php

namespace Tests\Feature;

use App\Contracts\PriceScraperInterface;
use App\Events\PriceChanged;
use App\Events\SubscriptionVerified;
use App\Listeners\CheckListingPrice;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckListingPriceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PriceScraperInterface&MockInterface $scraper;

    private CheckListingPrice $listener;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var PriceScraperInterface&MockInterface $scraper */
        $scraper = $this->mock(PriceScraperInterface::class);
        $this->scraper = $scraper;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->listener = $this->app->make(CheckListingPrice::class);
    }

    public function test_checks_price_for_unchecked_listing(): void
    {
        $listing = Listing::factory()->unchecked()->create();
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1500.0);

        $this->listener->handle(new SubscriptionVerified($subscription));

        $this->assertSame(1500.0, (float) $listing->fresh()->current_price);
    }

    public function test_dispatches_mail_job_when_unchecked_listing_gets_first_price(): void
    {
        $newPrice = 1500.0;

        $listing = Listing::factory()->unchecked()->create();
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn($newPrice);

        Event::fake();

        $this->listener->handle(new SubscriptionVerified($subscription));

        Event::assertDispatched(PriceChanged::class, static function (PriceChanged $event) use ($listing, $newPrice): bool {
            return $event->listing->id === $listing->id
                && $event->oldPrice === null
                && $event->newPrice === $newPrice;
        });
    }

    public function test_checks_price_for_stale_listing(): void
    {
        $listing = Listing::factory()->create([
            'current_price' => 1000.0,
            'last_checked_at' => now()->subHours(2),
        ]);
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1200.0);

        $this->listener->handle(new SubscriptionVerified($subscription));

        $this->assertSame(1200.0, (float) $listing->fresh()->current_price);
    }

    public function test_dispatches_mail_job_when_price_changed_on_stale_listing(): void
    {
        $oldPrice = 1000.0;
        $newPrice = 1200.0;

        $listing = Listing::factory()->create([
            'current_price' => $oldPrice,
            'last_checked_at' => now()->subHours(2),
        ]);
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn($newPrice);

        Event::fake();

        $this->listener->handle(new SubscriptionVerified($subscription));

        Event::assertDispatched(PriceChanged::class, static function (PriceChanged $event) use ($listing, $oldPrice, $newPrice): bool {
            return $event->listing->id === $listing->id
                && $event->oldPrice === $oldPrice
                && $event->newPrice === $newPrice;
        });
    }

    public function test_does_not_dispatch_mail_job_when_price_unchanged_on_stale_listing(): void
    {
        $listing = Listing::factory()->create([
            'current_price' => 1000.0,
            'last_checked_at' => now()->subHours(2),
        ]);
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1000.0);

        Bus::fake();

        $this->listener->handle(new SubscriptionVerified($subscription));

        Bus::assertNothingDispatched();
    }

    public function test_does_not_dispatch_mail_job_when_listing_recently_checked_with_same_price(): void
    {
        $listing = Listing::factory()->create([
            'current_price' => 1000.0,
            'last_checked_at' => now()->subMinutes(15),
        ]);
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        $this->scraper->shouldNotReceive('fetchPrice');

        Bus::fake();

        $this->listener->handle(new SubscriptionVerified($subscription));

        Bus::assertNothingDispatched();
    }

    public function test_does_not_dispatch_mail_job_for_deactivated_listing(): void
    {
        $listing = Listing::factory()->create(['deactivated_at' => now()]);
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        $this->scraper->shouldNotReceive('fetchPrice');

        Bus::fake();

        $this->listener->handle(new SubscriptionVerified($subscription));

        Bus::assertNothingDispatched();
    }

    public function test_does_not_dispatch_mail_job_directly_when_price_changes(): void
    {
        // Regression test: CheckListingPrice must delegate mail dispatch exclusively via
        // the PriceChanged event -> SendPriceChangeNotifications listener.
        // A direct dispatch(new SendPriceChangeMail) from this listener would send the mail twice.
        $listing = Listing::factory()->create([
            'current_price' => 1000.0,
            'last_checked_at' => now()->subHours(2),
        ]);
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1200.0);

        Event::fake();
        Bus::fake();

        $this->listener->handle(new SubscriptionVerified($subscription));

        Bus::assertNothingDispatched();
        Event::assertDispatched(PriceChanged::class);
    }
}
