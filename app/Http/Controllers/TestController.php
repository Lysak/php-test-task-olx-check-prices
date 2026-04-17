<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function subscribe(): JsonResponse
    {
        $subscription = $this->subscriptionService->subscribe(
            url: 'https://www.olx.ua/d/uk/obyavlenie/velosiped-strida-evo-3-na-peredachah-3-skorosti-IDYDSJt.html',
            email: 'me@example.localhost',
        );

        return response()->json([
            'message' => 'Test subscription created',
            'subscription_id' => $subscription->id,
            'verified' => $subscription->isVerified(),
        ]);
    }
}
