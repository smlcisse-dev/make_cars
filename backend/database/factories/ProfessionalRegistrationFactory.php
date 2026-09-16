<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalRegistration>
 */
class ProfessionalRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->garagiste(),
            'structure_name' => fake()->company(),
            'address' => fake()->address(),
            'business_registration_number' => strtoupper(fake()->bothify('IFU-########')),
            'status' => RegistrationStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function marketSpace(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->state(['role' => AccountType::MarketSpace]),
        ]);
    }
}
