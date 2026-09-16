<?php

namespace Tests\Feature\Garage;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_garagiste_can_list_its_garages_appointments(): void
    {
        $garage = Garage::factory()->create();
        Appointment::factory()->forGarage($garage)->count(2)->create();
        Appointment::factory()->count(1)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/appointments');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_garagiste_can_filter_its_appointments_by_status(): void
    {
        $garage = Garage::factory()->create();
        Appointment::factory()->forGarage($garage)->confirmed()->create();
        Appointment::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/appointments?status=pending');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_garagiste_can_confirm_a_pending_appointment(): void
    {
        $garage = Garage::factory()->create();
        $appointment = Appointment::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/appointments/{$appointment->id}/confirm");

        $response->assertOk()->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);
        $this->assertNotNull($appointment->fresh()->confirmed_at);
    }

    public function test_a_garagiste_can_reject_a_pending_appointment_with_a_reason(): void
    {
        $garage = Garage::factory()->create();
        $appointment = Appointment::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/appointments/{$appointment->id}/reject", [
            'reason' => 'Agenda complet ce jour-là.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', AppointmentStatus::Rejected->value);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'rejection_reason' => 'Agenda complet ce jour-là.',
        ]);
    }

    public function test_a_garagiste_can_reject_a_pending_appointment_without_a_reason(): void
    {
        $garage = Garage::factory()->create();
        $appointment = Appointment::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Rejected->value);
    }

    public function test_a_garagiste_can_propose_a_new_date_for_a_pending_appointment(): void
    {
        $garage = Garage::factory()->create();
        $appointment = Appointment::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $proposedAt = now()->addDays(10)->toIso8601String();

        $response = $this->postJson("/api/garage/appointments/{$appointment->id}/reschedule", [
            'proposed_at' => $proposedAt,
        ]);

        $response->assertOk()->assertJsonPath('data.status', AppointmentStatus::Rescheduled->value);
        $this->assertNotNull($appointment->fresh()->proposed_at);
    }

    public function test_confirming_an_already_confirmed_appointment_is_forbidden(): void
    {
        $garage = Garage::factory()->create();
        $appointment = Appointment::factory()->forGarage($garage)->confirmed()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/confirm")->assertForbidden();
    }

    public function test_a_garagiste_cannot_manage_another_garages_appointment(): void
    {
        $appointment = Appointment::factory()->create();
        $otherGarage = Garage::factory()->create();
        Sanctum::actingAs($otherGarage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/confirm")->assertNotFound();
    }

    public function test_a_non_garagiste_cannot_access_the_garage_appointment_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/appointments')->assertForbidden();
    }
}
