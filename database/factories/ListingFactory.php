<?php

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => 'https://www.olx.ua/d/uk/obyavlenie/' . $this->faker->slug() . '-' . $this->faker->bothify('ID#######') . '.html',
            'title' => $this->faker->sentence(4),
            'current_price' => $this->faker->randomFloat(2, 100, 50000),
            'last_checked_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function unchecked(): static
    {
        return $this->state(['last_checked_at' => null, 'current_price' => null]);
    }
}
