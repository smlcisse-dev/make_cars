<?php

namespace Tests\Feature\Garage;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_garagiste_without_an_approved_registration_has_no_garage_profile_yet(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $response = $this->getJson('/api/garage/profile');

        $response->assertNotFound();
    }

    public function test_a_garagiste_can_view_its_own_garage_profile(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/profile');

        $response->assertOk()->assertJsonPath('data.id', $garage->id);
    }

    public function test_a_garagiste_can_update_its_garage_profile(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->putJson('/api/garage/profile', [
            'name' => 'Garage Awa Réparation',
            'description' => 'Spécialiste climatisation.',
            'address' => 'Fidjrossè, Cotonou',
            'latitude' => 6.3703,
            'longitude' => 2.3912,
            'phone' => '+22997000000',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Garage Awa Réparation');
        $this->assertDatabaseHas('garages', [
            'id' => $garage->id,
            'name' => 'Garage Awa Réparation',
            'address' => 'Fidjrossè, Cotonou',
        ]);
    }

    public function test_a_garagiste_cannot_view_another_garages_profile_via_the_endpoint(): void
    {
        Garage::factory()->create();
        $otherGaragiste = User::factory()->garagiste()->create();
        Sanctum::actingAs($otherGaragiste);

        $this->getJson('/api/garage/profile')->assertNotFound();
    }

    public function test_a_non_garagiste_cannot_access_the_garage_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/profile')->assertForbidden();
    }
}
