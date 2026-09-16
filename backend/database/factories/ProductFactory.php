<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sellable_type' => Garage::class,
            'sellable_id' => Garage::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'price' => fake()->randomFloat(2, 1000, 50000),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'status' => ProductStatus::Pending,
        ];
    }

    public function forGarage(?Garage $garage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sellable_type' => Garage::class,
            'sellable_id' => $garage?->id ?? Garage::factory(),
        ]);
    }

    public function forMarketSpace(?MarketSpaceAccount $account = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sellable_type' => MarketSpaceAccount::class,
            'sellable_id' => $account?->id ?? MarketSpaceAccount::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }
}
