<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
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
            'appointment_id' => null,
            'status' => QuoteStatus::Draft,
        ];
    }

    /**
     * Devis rattaché à un RDV, pour la traçabilité (CLAUDE.md §5, ajout
     * v0.9) : garage/client sont dérivés du RDV pour rester cohérents.
     */
    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_id' => $appointment->garage_id,
            'user_id' => $appointment->user_id,
            'appointment_id' => $appointment->id,
        ]);
    }

    public function forGarage(Garage $garage): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_id' => $garage->id,
        ]);
    }

    public function forClient(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $client->id,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Sent,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Accepted,
        ]);
    }

    public function invoiced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Invoiced,
            'paid_at' => now(),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Abandoned,
        ]);
    }
}
