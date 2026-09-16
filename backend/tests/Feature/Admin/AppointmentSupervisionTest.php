<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_all_appointments(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Appointment::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/appointments');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_admin_can_filter_appointments_by_garage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $garage = Garage::factory()->create();
        Appointment::factory()->forGarage($garage)->create();
        Appointment::factory()->create();

        $response = $this->getJson("/api/admin/appointments?garage_id={$garage->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_admin_can_view_any_appointment(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $appointment = Appointment::factory()->create();

        $response = $this->getJson("/api/admin/appointments/{$appointment->id}");

        $response->assertOk()->assertJsonPath('data.id', $appointment->id);
    }

    public function test_a_non_admin_cannot_list_appointments_via_the_admin_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/appointments')->assertForbidden();
    }
}
