<?php

namespace Tests\Feature\Garage;

use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Compte automobiliste "express" créé par un garagiste pour un client
 * walk-in sans app (CLAUDE.md §5, ajout v0.9).
 */
class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_a_garagiste_can_create_an_express_client(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/clients/express', [
            'name' => 'Client Walk-in',
            'email' => 'walkin@example.com',
            'phone' => '+22900000001',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'walkin@example.com',
            'phone' => '+22900000001',
            'role' => 'automobiliste',
            'is_express' => true,
        ]);
    }

    public function test_creating_an_express_client_attaches_to_an_existing_automobiliste_by_email(): void
    {
        $garage = $this->approvedGarage();
        $existing = User::factory()->create(['email' => 'known@example.com']);
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/clients/express', [
            'name' => 'Nom Différent',
            'email' => 'known@example.com',
            'phone' => '+22900000002',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $existing->id);
        // Aucun nouveau compte ne doit être créé : le rattachement réutilise le client existant.
        $this->assertDatabaseCount('users', 3);
    }

    public function test_creating_an_express_client_attaches_to_an_existing_automobiliste_by_phone(): void
    {
        $garage = $this->approvedGarage();
        $existing = User::factory()->create(['phone' => '+22900000003']);
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/clients/express', [
            'name' => 'Nom Différent',
            'email' => 'unmatched@example.com',
            'phone' => '+22900000003',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $existing->id);
    }

    public function test_creating_an_express_client_is_refused_when_it_matches_a_professional_account(): void
    {
        $garage = $this->approvedGarage();
        User::factory()->garagiste()->create(['email' => 'pro@example.com']);
        Sanctum::actingAs($garage->user);

        $this->postJson('/api/garage/clients/express', [
            'name' => 'Tentative',
            'email' => 'pro@example.com',
            'phone' => '+22900000004',
        ])->assertUnprocessable();
    }

    public function test_express_client_creation_requires_name_email_and_phone(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs($garage->user);

        $this->postJson('/api/garage/clients/express', [])->assertUnprocessable();
    }
}
