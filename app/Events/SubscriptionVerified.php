<?php

namespace App\Events;

use App\Models\Subscription;

final readonly class SubscriptionVerified
{
    public function __construct(public Subscription $subscription) {}
}
