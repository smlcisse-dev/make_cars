<?php

namespace Database\Factories;

use App\Models\MarketSpaceAccount;
use App\Models\User;
use Database\Factories\Concerns\CompletesProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketSpaceAccount>
 */
class MarketSpaceAccountFactory extends Factory
{
    use CompletesProfile;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->marketSpace(),
            'name' => fake()->company(),
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(6.3, 6.5),
            'longitude' => fake()->longitude(2.3, 2.5),
            'phone' => '+22901'.fake()->numerify('########'),
        ];
    }
}
