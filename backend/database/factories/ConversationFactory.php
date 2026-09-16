<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Garage;
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
            'garage_id' => Garage::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function between(Garage $garage, User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_id' => $garage->id,
            'user_id' => $user->id,
        ]);
    }
}
