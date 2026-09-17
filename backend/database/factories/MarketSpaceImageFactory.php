<?php

namespace Database\Factories;

use App\Models\MarketSpaceAccount;
use App\Models\MarketSpaceImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketSpaceImage>
 */
class MarketSpaceImageFactory extends Factory
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
            'disk' => 'public',
            'path' => 'market-space/'.fake()->uuid().'.jpg',
            'position' => 0,
        ];
    }
}
