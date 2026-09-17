<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\MarketSpaceAccount;
use App\Models\MarketSpaceOpeningHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketSpaceOpeningHour>
 */
class MarketSpaceOpeningHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_space_account_id' => MarketSpaceAccount::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'is_closed' => false,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
