<?php

namespace Tests\Feature;

use App\Contracts\PriceScraperInterface;
use App\Mail\VerificationMail;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PriceScraperInterface&MockInterface $scraper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->scraper = $this->mock(PriceScraperInterface::class);
        /** @var Expectation $expectation */
        $expectation = $this->scraper->shouldReceive('fetchPrice');
        $expectation->andReturn(1500.0)->byDefault();
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
        $subscription = Subscription::factory()->create();

        $this->getJson("/verify/{$subscription->token}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNotNull($subscription->fresh()->verified_at);
    }

    public function test_verify_returns_404_for_invalid_token(): void
    {
        $this->getJson('/verify/invalid-token-that-does-not-exist')
            ->assertNotFound();
    }
}
