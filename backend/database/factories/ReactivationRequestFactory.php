<?php

namespace Database\Factories;

use App\Enums\ReactivationRequestStatus;
use App\Models\ProfessionalRegistration;
use App\Models\ReactivationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReactivationRequest>
 */
class ReactivationRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professional_registration_id' => ProfessionalRegistration::factory()->approved()->suspended(),
            'message' => fake()->sentence(12),
            'status' => ReactivationRequestStatus::Pending,
        ];
    }

    public function refused(string $reason = 'Corrections insuffisantes.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReactivationRequestStatus::Refused,
            'response_reason' => $reason,
            'decided_by' => User::factory()->admin(),
            'decided_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReactivationRequestStatus::Accepted,
            'decided_by' => User::factory()->admin(),
            'decided_at' => now(),
        ]);
    }
}
