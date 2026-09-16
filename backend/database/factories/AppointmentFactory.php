<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'description' => fake()->sentence(),
            'requested_at' => now()->addDays(fake()->numberBetween(1, 14)),
            'status' => AppointmentStatus::Pending,
        ];
    }

    public function forGarage(?Garage $garage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_id' => $garage?->id ?? Garage::factory(),
        ]);
    }

    public function forService(RepairService $service): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_id' => $service->garage_id,
            'repair_service_id' => $service->id,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Confirmed,
            'confirmed_at' => $attributes['requested_at'],
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function rescheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Rescheduled,
            'proposed_at' => now()->addDays(fake()->numberBetween(15, 21)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'confirmed_at' => $attributes['requested_at'],
        ]);
    }
}
