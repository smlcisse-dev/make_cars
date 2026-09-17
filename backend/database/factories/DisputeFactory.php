<?php

namespace Database\Factories;

use App\Enums\DisputeResolutionAction;
use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'respondent_type' => Garage::class,
            'respondent_id' => Garage::factory(),
            'transaction_type' => Quote::class,
            'transaction_id' => Quote::factory()->invoiced(),
            'user_id' => User::factory(),
            'reason' => $this->faker->paragraph(),
            'status' => DisputeStatus::Submitted,
        ];
    }

    public function forGarage(Garage $garage): static
    {
        return $this->state(fn (array $attributes) => [
            'respondent_type' => Garage::class,
            'respondent_id' => $garage->id,
        ]);
    }

    public function forMarketSpace(MarketSpaceAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'respondent_type' => MarketSpaceAccount::class,
            'respondent_id' => $account->id,
        ]);
    }

    public function forTransaction(Quote|Order $transaction): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => $transaction::class,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function forClient(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $client->id,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisputeStatus::UnderReview,
            'response_requested_at' => now(),
            'response_requested_by' => User::factory()->admin(),
        ]);
    }

    public function resolvedRejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisputeStatus::ResolvedRejected,
            'resolution_reason' => $this->faker->sentence(),
            'decided_by' => User::factory()->admin(),
            'decided_at' => now(),
        ]);
    }

    public function resolvedFounded(DisputeResolutionAction $action = DisputeResolutionAction::Warning): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisputeStatus::ResolvedFounded,
            'resolution_reason' => $this->faker->sentence(),
            'resolution_action' => $action,
            'decided_by' => User::factory()->admin(),
            'decided_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisputeStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
