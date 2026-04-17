<?php

namespace Tests\Feature;

use App\Contracts\PriceScraperInterface;
use App\Mail\VerificationMail;
use App\Models\Listing;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PriceScraperInterface&MockInterface $scraper;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scraper = $this->mock(PriceScraperInterface::class);
        $this->service = $this->app->make(SubscriptionService::class);
    }

    public function test_subscribe_creates_listing_and_subscription_for_new_url(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1500.0);
        Mail::fake();

        $subscription = $this->service->subscribe('https://www.olx.ua/new-ad', 'user@example.com');

        $this->assertModelExists($subscription);
        $this->assertDatabaseHas('listings', ['url' => 'https://www.olx.ua/new-ad']);
        $this->assertSame('user@example.com', $subscription->email);
    }

    public function test_subscribe_fetches_price_for_new_listing(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(1500.0);
        Mail::fake();

        $this->service->subscribe('https://www.olx.ua/new-ad', 'user@example.com');

        $listing = Listing::where('url', 'https://www.olx.ua/new-ad')->first();
        $this->assertSame(1500.0, (float) $listing->current_price);
    }

    public function test_subscribe_does_not_fetch_price_for_existing_listing(): void
    {
        Listing::factory()->create(['url' => 'https://www.olx.ua/existing-ad']);
        $this->scraper->shouldNotReceive('fetchPrice');
        Mail::fake();

        $this->service->subscribe('https://www.olx.ua/existing-ad', 'user@example.com');
    }

    public function test_subscribe_sends_verification_email_for_new_subscription(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->once()->andReturn(null);
        Mail::fake();

        $this->service->subscribe('https://www.olx.ua/new-ad', 'user@example.com');

        Mail::assertQueued(VerificationMail::class, static fn (VerificationMail $mail): bool => $mail->hasTo('user@example.com'));
    }

    public function test_subscribe_does_not_send_email_for_duplicate_subscription(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->create(['listing_id' => $listing->id, 'email' => 'user@example.com']);
        $this->scraper->shouldNotReceive('fetchPrice');
        Mail::fake();

        $this->service->subscribe($listing->url, 'user@example.com');

        Mail::assertNothingQueued();
    }

    public function test_subscribe_returns_existing_subscription_for_duplicate(): void
    {
        $listing = Listing::factory()->create();
        $existing = Subscription::factory()->create(['listing_id' => $listing->id, 'email' => 'user@example.com']);
        $this->scraper->shouldNotReceive('fetchPrice');
        Mail::fake();

        $returned = $this->service->subscribe($listing->url, 'user@example.com');

        $this->assertEquals($existing->id, $returned->id);
        $this->assertCount(1, Subscription::all());
    }

    public function test_verify_sets_verified_at_for_valid_token(): void
    {
        $subscription = Subscription::factory()->create();

        $this->service->verify($subscription->token);

        $this->assertNotNull($subscription->fresh()->verified_at);
    }

    public function test_verify_is_idempotent_for_already_verified_subscription(): void
    {
        $subscription = Subscription::factory()->verified()->create();
        $originalVerifiedAt = $subscription->verified_at->toDateTimeString();

        $this->travel(10)->seconds();

        $this->service->verify($subscription->token);

        $this->assertSame($originalVerifiedAt, $subscription->fresh()->verified_at->toDateTimeString());
    }

    public function test_verify_throws_for_invalid_token(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->verify('non-existent-token');
    }
}
