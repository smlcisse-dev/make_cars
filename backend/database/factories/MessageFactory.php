<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }

    public function inConversation(Conversation $conversation): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function from(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $user->id,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => null,
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'body' => null,
            'image_disk' => 'local',
            'image_path' => 'chat-attachments/test/'.fake()->uuid().'.jpg',
        ]);
    }
}
