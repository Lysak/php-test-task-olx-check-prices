<?php

namespace App\Jobs;

use App\Mail\ListingUnavailableMail;
use App\Models\Listing;
use App\Models\Subscription;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

final class SendListingUnavailableMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Listing $listing,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(24);
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('mail')];
    }

    public function handle(): void
    {
        Mail::to($this->subscription->email)
            ->send(new ListingUnavailableMail($this->listing));
    }
}
