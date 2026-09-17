<?php

namespace Database\Factories;

use App\Enums\PushNotificationStatus;
use App\Enums\PushNotificationType;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => PushNotificationType::AppointmentRequested,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'data' => [],
            'status' => PushNotificationStatus::Created,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function ofType(PushNotificationType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PushNotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
