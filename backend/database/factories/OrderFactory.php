<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
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

    public function forClient(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $client->id,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
