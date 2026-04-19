<?php

namespace App\Jobs;

use App\Mail\UnsubscribedMail;
use App\Support\RateLimiterKey;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

final class SendUnsubscribedMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public function __construct(public readonly string $email) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(24);
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited(RateLimiterKey::MAIL)];
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new UnsubscribedMail);
    }
}
