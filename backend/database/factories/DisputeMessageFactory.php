<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputeMessage>
 */
class DisputeMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dispute_id' => Dispute::factory(),
            'author_id' => User::factory()->admin(),
            'body' => $this->faker->paragraph(),
        ];
    }

    public function forDispute(Dispute $dispute): static
    {
        return $this->state(fn (array $attributes) => [
            'dispute_id' => $dispute->id,
        ]);
    }

    public function by(User $author): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $author->id,
        ]);
    }
}
