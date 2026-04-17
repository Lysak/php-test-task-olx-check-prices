<?php

namespace Tests\Feature;

use App\Contracts\PriceScraperInterface;
use App\Events\PriceChanged;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\PendingCommand;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckPricesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PriceScraperInterface&MockInterface $scraper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scraper = $this->mock(PriceScraperInterface::class);
    }

    public function test_outputs_message_when_no_active_subscriptions(): void
    {
        $this->scraper->shouldNotReceive('fetchPrice');

        /** @var PendingCommand $command */
        $command = $this->artisan('prices:check');
        $command->expectsOutput('No active subscriptions found.')
            ->assertSuccessful();
    }

    public function test_calls_check_for_each_listing_with_verified_subscriptions(): void
    {
        $listings = Listing::factory()->count(3)->create(['current_price' => 1000.0]);
        $listings->each(static fn (Listing $l) => Subscription::factory()->verified()->create(['listing_id' => $l->id]));

        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->times(3)->andReturn(1200.0);

        Event::fake();

        /** @var PendingCommand $command */
        $command = $this->artisan('prices:check');
        $command->assertSuccessful();

        Event::assertDispatched(PriceChanged::class, 3);
    }

    public function test_skips_deactivated_listings(): void
    {
        $listing = Listing::factory()->create(['deactivated_at' => now()]);
        Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        $this->scraper->shouldNotReceive('fetchPrice');

        /** @var PendingCommand $command */
        $command = $this->artisan('prices:check');
        $command->expectsOutput('No active subscriptions found.')
            ->assertSuccessful();
    }

    public function test_skips_listings_with_only_unverified_subscriptions(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->create(['listing_id' => $listing->id]);

        $this->scraper->shouldNotReceive('fetchPrice');

        /** @var PendingCommand $command */
        $command = $this->artisan('prices:check');
        $command->expectsOutput('No active subscriptions found.')
            ->assertSuccessful();
    }
}
