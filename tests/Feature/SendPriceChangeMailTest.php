<?php

namespace Tests\Feature;

use App\Jobs\SendPriceChangeMail;
use App\Mail\PriceChangedMail;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPriceChangeMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_price_changed_mail_to_subscriber(): void
    {
        $oldPrice = 1000.0;
        $newPrice = 1200.0;

        $listing = Listing::factory()->create();
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        Mail::fake();

        new SendPriceChangeMail($subscription, $listing, $oldPrice, $newPrice)->handle();

        Mail::assertQueued(PriceChangedMail::class, static function (PriceChangedMail $mail) use ($subscription, $oldPrice, $newPrice): bool {
            return $mail->hasTo($subscription->email)
                && $mail->oldPrice === $oldPrice
                && $mail->newPrice === $newPrice;
        });
    }

    public function test_sends_price_email_with_null_old_price_on_first_check(): void
    {
        $newPrice = 1500.0;

        $listing = Listing::factory()->create();
        $subscription = Subscription::factory()->verified()->create(['listing_id' => $listing->id]);

        Mail::fake();

        new SendPriceChangeMail($subscription, $listing, null, $newPrice)->handle();

        Mail::assertQueued(PriceChangedMail::class, static function (PriceChangedMail $mail) use ($subscription, $newPrice): bool {
            return $mail->hasTo($subscription->email)
                && $mail->oldPrice === null
                && $mail->newPrice === $newPrice;
        });
    }
}
