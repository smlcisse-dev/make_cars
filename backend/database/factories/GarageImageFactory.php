<?php

namespace Database\Factories;

use App\Models\Garage;
use App\Models\GarageImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarageImage>
 */
class GarageImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'garage_id' => Garage::factory(),
            'disk' => 'public',
            'path' => 'garages/'.fake()->uuid().'.jpg',
            'position' => 0,
        ];
    }
}
