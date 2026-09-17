<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reviewable_type' => Garage::class,
            'reviewable_id' => Garage::factory(),
            'transaction_type' => Quote::class,
            'transaction_id' => Quote::factory()->invoiced(),
            'user_id' => User::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentence(),
            'status' => ReviewStatus::Visible,
        ];
    }

    public function forGarage(Garage $garage): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewable_type' => Garage::class,
            'reviewable_id' => $garage->id,
        ]);
    }

    public function forMarketSpace(MarketSpaceAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewable_type' => MarketSpaceAccount::class,
            'reviewable_id' => $account->id,
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

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Hidden,
            'moderation_reason' => $this->faker->sentence(),
            'moderated_by' => User::factory()->admin(),
            'moderated_at' => now(),
        ]);
    }
}
