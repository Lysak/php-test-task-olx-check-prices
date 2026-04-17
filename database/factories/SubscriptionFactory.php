<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'token' => Str::uuid()->toString(),
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(['verified_at' => now()]);
    }
}
