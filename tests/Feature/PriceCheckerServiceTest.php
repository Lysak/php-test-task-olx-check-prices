<?php

namespace Tests\Feature;

use App\Contracts\PriceScraperInterface;
use App\Events\ListingUnavailable;
use App\Events\PriceChanged;
use App\Models\Listing;
use App\Services\PriceCheckerService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\Expectation;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PriceCheckerServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PriceScraperInterface&MockInterface $scraper;

    private PriceCheckerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scraper = $this->mock(PriceScraperInterface::class);
        $this->service = new PriceCheckerService(
            $this->scraper,
            $this->app->make(LoggerInterface::class),
        );
    }

    public function test_stores_first_price_without_firing_event(): void
    {
        $listing = Listing::factory()->unchecked()->create();
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1500.0);

        Event::fake();

        $this->service->check($listing);

        Event::assertNotDispatched(PriceChanged::class);
        $this->assertSame(1500.0, (float) $listing->fresh()->current_price);
        $this->assertCount(1, $listing->priceHistories);
    }

    public function test_does_nothing_when_price_unchanged(): void
    {
        $listing = Listing::factory()->create(['current_price' => 1000.0]);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1000.0);

        Event::fake();

        $this->service->check($listing);

        Event::assertNotDispatched(PriceChanged::class);
        $this->assertCount(0, $listing->priceHistories);
    }

    public function test_fires_price_changed_event_and_stores_history(): void
    {
        $oldPrice = 1000.0;
        $newPrice = 1200.0;

        $listing = Listing::factory()->create(['current_price' => $oldPrice]);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn($newPrice);

        Event::fake();

        $this->service->check($listing);

        Event::assertDispatched(PriceChanged::class, static function (PriceChanged $event) use ($listing, $oldPrice, $newPrice): bool {
            return $event->listing->id === $listing->id
                && $event->oldPrice === $oldPrice
                && $event->newPrice === $newPrice;
        });

        $this->assertSame(1200.0, (float) $listing->fresh()->current_price);
        $this->assertCount(1, $listing->priceHistories);
    }

    public function test_resets_consecutive_failures_on_successful_fetch(): void
    {
        $listing = Listing::factory()->create([
            'current_price' => 1000.0,
            'consecutive_failures' => 2,
        ]);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1000.0);

        $this->service->check($listing);

        $this->assertSame(0, $listing->fresh()->consecutive_failures);
    }

    public function test_increments_consecutive_failures_when_scraper_returns_null(): void
    {
        $listing = Listing::factory()->create(['consecutive_failures' => 1]);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(null);

        Event::fake();

        $this->service->check($listing);

        $this->assertSame(2, $listing->fresh()->consecutive_failures);
        Event::assertNotDispatched(ListingUnavailable::class);
    }

    public function test_does_not_deactivate_listing_below_threshold(): void
    {
        $listing = Listing::factory()->create(['consecutive_failures' => 1]);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(null);

        Event::fake();

        $this->service->check($listing);

        $this->assertNull($listing->fresh()->deactivated_at);
        Event::assertNotDispatched(ListingUnavailable::class);
    }

    public function test_deactivates_listing_and_fires_event_at_failure_threshold(): void
    {
        $listing = Listing::factory()->create(['consecutive_failures' => 2]);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(null);

        Event::fake();

        $this->service->check($listing);

        $this->assertNotNull($listing->fresh()->deactivated_at);
        Event::assertDispatched(ListingUnavailable::class, static function (ListingUnavailable $event) use ($listing): bool {
            return $event->listing->id === $listing->id;
        });
    }
}
