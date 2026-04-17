<?php

namespace App\Jobs;

use App\Mail\PriceChangedMail;
use App\Models\Listing;
use App\Models\Subscription;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

final class SendPriceChangeMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Listing $listing,
        public readonly float $oldPrice,
        public readonly float $newPrice,
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
            ->send(new PriceChangedMail($this->listing, $this->oldPrice, $this->newPrice));
    }
}
