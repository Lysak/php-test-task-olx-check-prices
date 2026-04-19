<?php

namespace Tests\Feature;

use App\Events\SubscriptionVerified;
use App\Jobs\SendUnsubscribedMail;
use App\Mail\VerificationMail;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Mail::fake();
    }

    public function test_subscribe_returns_200_with_valid_data(): void
    {
        $response = $this->postJson('/subscribe', [
            'url' => 'https://www.olx.ua/d/uk/obyavlenie/test-IDabc123.html',
            'email' => 'user@example.com',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_subscribe_creates_subscription_and_sends_verification(): void
    {
        $this->postJson('/subscribe', [
            'url' => 'https://www.olx.ua/d/uk/obyavlenie/test-IDabc123.html',
            'email' => 'user@example.com',
        ]);

        $this->assertDatabaseHas('subscriptions', ['email' => 'user@example.com']);
        Mail::assertQueued(VerificationMail::class);
    }

    public function test_subscribe_returns_422_without_url(): void
    {
        $this->postJson('/subscribe', ['email' => 'user@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_subscribe_returns_422_with_invalid_url(): void
    {
        $this->postJson('/subscribe', ['url' => 'not-a-url', 'email' => 'user@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_subscribe_returns_422_without_email(): void
    {
        $this->postJson('/subscribe', ['url' => 'https://www.olx.ua/ad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_subscribe_returns_422_with_invalid_email(): void
    {
        $this->postJson('/subscribe', ['url' => 'https://www.olx.ua/ad', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verify_returns_200_for_valid_token(): void
    {
        Event::fake();

        $listing = Listing::factory()->unchecked()->create();
        $subscription = Subscription::factory()->create(['listing_id' => $listing->id]);

        $this->getJson("/verify/{$subscription->token}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNotNull($subscription->fresh()->verified_at);
        Event::assertDispatched(SubscriptionVerified::class);
    }

    public function test_verify_returns_404_for_invalid_token(): void
    {
        $this->getJson('/verify/invalid-token-that-does-not-exist')
            ->assertNotFound();
    }

    public function test_unsubscribe_returns_200_and_soft_deletes_subscription(): void
    {
        Bus::fake();

        $subscription = Subscription::factory()->verified()->create();

        $this->getJson("/unsubscribe/{$subscription->token}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSoftDeleted($subscription);
        Bus::assertDispatched(SendUnsubscribedMail::class, static fn (SendUnsubscribedMail $job): bool => $job->email === $subscription->email);
    }

    public function test_unsubscribe_returns_404_for_invalid_token(): void
    {
        $this->getJson('/unsubscribe/invalid-token-that-does-not-exist')
            ->assertNotFound();
    }
}
