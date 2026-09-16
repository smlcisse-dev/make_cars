<?php

namespace Database\Factories;

use App\Enums\RegistrationDocumentType;
use App\Models\ProfessionalRegistration;
use App\Models\RegistrationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationDocument>
 */
class RegistrationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professional_registration_id' => ProfessionalRegistration::factory(),
            'type' => RegistrationDocumentType::BusinessRegistration,
            'disk' => 'local',
            'path' => 'registration-documents/'.fake()->uuid().'.pdf',
        ];
    }

    public function premisesPhoto(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RegistrationDocumentType::PremisesPhoto,
            'path' => 'registration-documents/'.fake()->uuid().'.jpg',
        ]);
    }
}
