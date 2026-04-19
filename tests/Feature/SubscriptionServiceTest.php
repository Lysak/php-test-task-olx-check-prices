<?php

namespace Tests\Feature;

use App\Events\SubscriptionVerified;
use App\Jobs\SendUnsubscribedMail;
use App\Mail\VerificationMail;
use App\Models\Listing;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(SubscriptionService::class);
    }

    public function test_subscribe_creates_listing_and_subscription_for_new_url(): void
    {
        Mail::fake();

        $subscription = $this->service->subscribe('https://www.olx.ua/new-ad', 'user@example.com');

        $this->assertModelExists($subscription);
        $this->assertDatabaseHas('listings', ['url' => 'https://www.olx.ua/new-ad']);
        $this->assertSame('user@example.com', $subscription->email);
    }

    public function test_subscribe_creates_listing_without_price(): void
    {
        Mail::fake();

        $this->service->subscribe('https://www.olx.ua/new-ad', 'user@example.com');

        $listing = Listing::where('url', 'https://www.olx.ua/new-ad')->first();
        $this->assertNull($listing->current_price);
    }

    public function test_subscribe_sends_verification_email_for_new_subscription(): void
    {
        Mail::fake();

        $this->service->subscribe('https://www.olx.ua/new-ad', 'user@example.com');

        Mail::assertQueued(VerificationMail::class, static fn (VerificationMail $mail): bool => $mail->hasTo('user@example.com'));
    }

    public function test_subscribe_does_not_send_email_for_duplicate_subscription(): void
    {
        $listing = Listing::factory()->create();
        Subscription::factory()->create(['listing_id' => $listing->id, 'email' => 'user@example.com']);
        Mail::fake();

        $this->service->subscribe($listing->url, 'user@example.com');

        Mail::assertNothingQueued();
    }

    public function test_subscribe_returns_existing_subscription_for_duplicate(): void
    {
        $listing = Listing::factory()->create();
        $existing = Subscription::factory()->create(['listing_id' => $listing->id, 'email' => 'user@example.com']);
        Mail::fake();

        $returned = $this->service->subscribe($listing->url, 'user@example.com');

        $this->assertEquals($existing->id, $returned->id);
        $this->assertCount(1, Subscription::all());
    }

    public function test_verify_sets_verified_at_for_valid_token(): void
    {
        Event::fake();

        $subscription = Subscription::factory()->create();

        $this->service->verify($subscription->token);

        $this->assertNotNull($subscription->fresh()->verified_at);
    }

    public function test_verify_dispatches_subscription_verified_event(): void
    {
        Event::fake();

        $subscription = Subscription::factory()->create();

        $this->service->verify($subscription->token);

        Event::assertDispatched(SubscriptionVerified::class, static fn (SubscriptionVerified $event): bool => $event->subscription->is($subscription));
    }

    public function test_verify_is_idempotent_for_already_verified_subscription(): void
    {
        Event::fake();

        $subscription = Subscription::factory()->verified()->create();
        $originalVerifiedAt = $subscription->verified_at->toDateTimeString();

        $this->travel(10)->seconds();

        $this->service->verify($subscription->token);

        $this->assertSame($originalVerifiedAt, $subscription->fresh()->verified_at->toDateTimeString());
        Event::assertNotDispatched(SubscriptionVerified::class);
    }

    public function test_verify_throws_for_invalid_token(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->verify('non-existent-token');
    }

    public function test_unsubscribe_soft_deletes_subscription_for_valid_token(): void
    {
        Bus::fake();

        $subscription = Subscription::factory()->create();

        $this->service->unsubscribe($subscription->token);

        $this->assertSoftDeleted($subscription);
        Bus::assertDispatched(SendUnsubscribedMail::class, static fn (SendUnsubscribedMail $job): bool => $job->email === $subscription->email);
    }

    public function test_unsubscribe_throws_for_invalid_token(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->unsubscribe('non-existent-token');
    }
}
