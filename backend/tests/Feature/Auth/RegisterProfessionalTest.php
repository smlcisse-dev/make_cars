<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegisterProfessionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_garagiste_can_register_with_justificatifs_and_stays_pending(): void
    {
        Storage::fake('local');

        $response = $this->postJson('/api/auth/register/professionnel', [
            'name' => 'Moussa Garagiste',
            'email' => 'moussa@garage.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'garagiste',
            'structure_name' => 'Garage Moussa',
            'address' => 'Cotonou, Bénin',
            'business_registration_number' => 'IFU-12345678',
            'business_registration_document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
            'premises_photos' => [
                UploadedFile::fake()->image('local.jpg'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', AccountType::Garagiste->value)
            ->assertJsonPath('data.user.professional_registration.status', RegistrationStatus::Pending->value);

        $this->assertDatabaseHas('professional_registrations', [
            'structure_name' => 'Garage Moussa',
            'status' => RegistrationStatus::Pending->value,
        ]);
        $this->assertDatabaseCount('registration_documents', 2);
    }

    public function test_registration_requires_at_least_one_premises_photo(): void
    {
        Storage::fake('local');

        $response = $this->postJson('/api/auth/register/professionnel', [
            'name' => 'Moussa Garagiste',
            'email' => 'moussa@garage.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'garagiste',
            'structure_name' => 'Garage Moussa',
            'address' => 'Cotonou, Bénin',
            'business_registration_number' => 'IFU-12345678',
            'business_registration_document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('premises_photos');
    }

    public function test_account_type_must_be_garagiste_or_market_space(): void
    {
        Storage::fake('local');

        $response = $this->postJson('/api/auth/register/professionnel', [
            'name' => 'Moussa Garagiste',
            'email' => 'moussa@garage.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'admin',
            'structure_name' => 'Garage Moussa',
            'address' => 'Cotonou, Bénin',
            'business_registration_number' => 'IFU-12345678',
            'business_registration_document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
            'premises_photos' => [UploadedFile::fake()->image('local.jpg')],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('account_type');
    }
}
