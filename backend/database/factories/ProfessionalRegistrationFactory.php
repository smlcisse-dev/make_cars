<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\RegistrationDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : dossier `pending` avec informations légales remplies (un
 * dossier en attente a forcément été soumis — CLAUDE.md §5, ajout v0.26).
 * Un état par statut ; `withProfile()` et `withBusinessRegistrationDocument()`
 * ajoutent le profil complet et le document quand un test en a besoin.
 *
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
            'business_registration_number' => 'RB/COT/'.fake()->numerify('## B #####'),
            'ifu' => fake()->numerify('#############'),
            'npi' => fake()->numerify('##########'),
            'status' => RegistrationStatus::Pending,
            'submitted_at' => now(),
        ];
    }

    public function profileIncomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_registration_number' => null,
            'ifu' => null,
            'npi' => null,
            'status' => RegistrationStatus::ProfileIncomplete,
            'submitted_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'suspension_reason' => fake()->sentence(),
            'suspended_by' => User::factory()->admin(),
            'suspended_at' => now(),
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

    /**
     * Profil Garage ou Market Space complet (selon le rôle) pour le compte
     * du dossier — prérequis de la soumission et source de
     * `structure_name`/`address` côté admin.
     */
    public function withProfile(): static
    {
        return $this->afterCreating(function (ProfessionalRegistration $registration) {
            $profileFactory = $registration->user->role === AccountType::MarketSpace
                ? MarketSpaceAccount::factory()
                : Garage::factory();

            $profileFactory->complete()->for($registration->user)->create();
        });
    }

    public function withBusinessRegistrationDocument(): static
    {
        return $this->afterCreating(function (ProfessionalRegistration $registration) {
            RegistrationDocument::factory()->for($registration)->create();
        });
    }
}
