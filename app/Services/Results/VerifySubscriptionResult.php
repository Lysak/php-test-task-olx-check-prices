<?php

namespace App\Services\Results;

use App\Enums\VerificationStatus;
use App\Models\Subscription;

final readonly class VerifySubscriptionResult
{
    public function __construct(
        public VerificationStatus $status,
        public Subscription $subscription,
        public string $message,
    ) {}

    public static function verified(Subscription $subscription): self
    {
        return new self(
            status: VerificationStatus::Verified,
            subscription: $subscription,
            message: 'Підписку активовано. Ви будете отримувати сповіщення про зміну ціни.',
        );
    }

    public static function alreadyVerified(Subscription $subscription): self
    {
        return new self(
            status: VerificationStatus::AlreadyVerified,
            subscription: $subscription,
            message: 'Підписка вже була активована раніше.',
        );
    }
}
