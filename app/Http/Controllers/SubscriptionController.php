<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscribeRequest;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $this->subscriptionService->subscribe(
            url: $request->validated('url'),
            email: $request->validated('email'),
        );

        return response()->json(['message' => 'Перевірте email для підтвердження підписки.']);
    }

    public function verify(string $token): JsonResponse
    {
        $this->subscriptionService->verify($token);

        return response()->json(['message' => 'Підписку активовано. Ви будете отримувати сповіщення про зміну ціни.']);
    }
}
