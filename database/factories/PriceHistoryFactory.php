<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\PriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'price' => $this->faker->randomFloat(2, 100, 50000),
            'recorded_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
