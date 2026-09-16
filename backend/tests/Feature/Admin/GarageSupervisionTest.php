<?php

namespace Tests\Feature\Admin;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarageSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_every_garage_regardless_of_validation_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Garage::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/garages');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_non_admin_cannot_list_garages_via_the_admin_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/garages')->assertForbidden();
    }
}
