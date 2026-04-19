<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Http\Requests\SubscribeRequest;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
        $result = $this->subscriptionService->verify($token);

        return match ($result->status) {
            VerificationStatus::Verified => response()->json(
                ['message' => $result->message],
                Response::HTTP_OK,
            ),
            VerificationStatus::AlreadyVerified => response()->json(
                ['message' => $result->message],
                Response::HTTP_CONFLICT,
            ),
        };
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $this->subscriptionService->unsubscribe($token);

        return response()->json(['message' => 'Підписку скасовано. Ви більше не будете отримувати сповіщення.']);
    }
}
