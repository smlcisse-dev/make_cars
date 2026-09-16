<?php

namespace Database\Factories;

use App\Enums\RepairServiceStatus;
use App\Enums\ServiceCategory;
use App\Models\Garage;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairService>
 */
class RepairServiceFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(ServiceCategory::cases()),
            'price' => fake()->randomFloat(2, 2000, 100000),
            'duration_minutes' => fake()->numberBetween(15, 240),
            'is_active' => true,
            'status' => RepairServiceStatus::Pending,
        ];
    }

    public function forGarage(?Garage $garage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_id' => $garage?->id ?? Garage::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RepairServiceStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RepairServiceStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
