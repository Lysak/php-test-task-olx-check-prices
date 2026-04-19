<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SubscriptionVerified;
use App\Jobs\SendUnsubscribedMail;
use App\Mail\VerificationMail;
use App\Models\Listing;
use App\Models\Subscription;
use App\Services\Results\VerifySubscriptionResult;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final readonly class SubscriptionService
{
    public function __construct(
        private Listing $listing,
        private Subscription $subscription,
    ) {}

    public function subscribe(string $url, string $email): Subscription
    {
        $listing = $this->listing->firstOrCreate(['url' => $url]);

        $subscription = $this->subscription->firstOrCreate(
            ['listing_id' => $listing->id, 'email' => $email],
            ['token' => Str::uuid()->toString()],
        );

        if ($subscription->wasRecentlyCreated) {
            Mail::to($email)->queue(new VerificationMail($subscription));
        }

        return $subscription;
    }

    public function verify(string $token): VerifySubscriptionResult
    {
        $subscription = $this->subscription->where('token', $token)->firstOrFail();

        if ($subscription->isVerified()) {
            return VerifySubscriptionResult::alreadyVerified($subscription);
        }

        $subscription->update([
            'verified_at' => now(),
        ]);

        event(new SubscriptionVerified($subscription));

        return VerifySubscriptionResult::verified($subscription->fresh());
    }

    public function unsubscribe(string $token): void
    {
        $subscription = $this->subscription->where('token', $token)->firstOrFail();
        $email = $subscription->email;

        $subscription->delete();

        dispatch(new SendUnsubscribedMail($email));
    }
}
