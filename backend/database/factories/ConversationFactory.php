<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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

    public function between(Garage|MarketSpaceAccount $sellable, User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'sellable_type' => $sellable->getMorphClass(),
            'sellable_id' => $sellable->id,
            'user_id' => $user->id,
        ]);
    }
}
