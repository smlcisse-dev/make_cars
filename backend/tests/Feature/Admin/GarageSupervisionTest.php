<?php

namespace Tests\Feature\Admin;

use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarageSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_lists_approved_garages_including_suspended_ones(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Garage::factory()->count(2)->withApprovedRegistration()->create();
        $suspended = ProfessionalRegistration::factory()->suspended()->create();
        Garage::factory()->for($suspended->user)->create();

        $response = $this->getJson('/api/admin/garages');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_garages_whose_dossier_is_not_approved_are_excluded_from_the_list_and_the_detail(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $approved = Garage::factory()->withApprovedRegistration()->create();

        foreach ([
            ProfessionalRegistration::factory()->profileIncomplete()->create(),
            ProfessionalRegistration::factory()->pending()->create(),
            ProfessionalRegistration::factory()->rejected()->create(),
        ] as $registration) {
            $garage = Garage::factory()->for($registration->user)->create();
            $this->getJson("/api/admin/garages/{$garage->id}")->assertNotFound();
        }

        $this->getJson('/api/admin/garages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);
        $this->getJson("/api/admin/garages/{$approved->id}")->assertOk();
    }

    public function test_a_non_admin_cannot_list_garages_via_the_admin_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/garages')->assertForbidden();
    }
}
