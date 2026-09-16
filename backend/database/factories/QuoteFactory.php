<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Quote;
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
            'appointment_id' => Appointment::factory()->confirmed(),
            'status' => QuoteStatus::Draft,
        ];
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_id' => $appointment->id,
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
