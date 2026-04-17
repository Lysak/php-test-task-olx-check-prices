<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PriceScraperInterface;
use App\Mail\VerificationMail;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final readonly class SubscriptionService
{
    public function __construct(
        private PriceScraperInterface $scraper,
        private Listing $listing,
        private Subscription $subscription,
    ) {}

    public function subscribe(string $url, string $email): Subscription
    {
        $listing = $this->listing->firstOrCreate(['url' => $url]);

        if ($listing->wasRecentlyCreated) {
            $price = $this->scraper->fetchPrice($url);
            $listing->update([
                'current_price' => $price,
                'last_checked_at' => now(),
            ]);
        }

        $subscription = $this->subscription->firstOrCreate(
            ['listing_id' => $listing->id, 'email' => $email],
            ['token' => Str::uuid()->toString()],
        );

        if ($subscription->wasRecentlyCreated) {
            Mail::to($email)->queue(new VerificationMail($subscription));
        }

        return $subscription;
    }

    public function verify(string $token): Subscription
    {
        $subscription = $this->subscription->where('token', $token)->firstOrFail();

        if (! $subscription->isVerified()) {
            $subscription->update(['verified_at' => now()]);
        }

        return $subscription;
    }
}
