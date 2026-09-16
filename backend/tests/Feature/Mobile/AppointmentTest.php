<?php

namespace Tests\Feature\Mobile;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_an_automobiliste_can_request_an_appointment_with_a_catalog_service(): void
    {
        $garage = $this->approvedGarage();
        $service = RepairService::factory()->forGarage($garage)->approved()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'repair_service_id' => $service->id,
            'requested_at' => now()->addDays(3)->toIso8601String(),
        ]);

        $response->assertCreated()->assertJsonPath('data.status', AppointmentStatus::Pending->value);
        $this->assertDatabaseHas('appointments', [
            'garage_id' => $garage->id,
            'repair_service_id' => $service->id,
            'status' => AppointmentStatus::Pending->value,
        ]);
    }

    public function test_an_automobiliste_can_request_an_appointment_with_free_text_only(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'description' => 'Bruit bizarre au freinage.',
            'requested_at' => now()->addDays(2)->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('appointments', [
            'garage_id' => $garage->id,
            'repair_service_id' => null,
            'description' => 'Bruit bizarre au freinage.',
        ]);
    }

    public function test_requesting_an_appointment_without_a_service_or_description_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'requested_at' => now()->addDays(2)->toIso8601String(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('description');
    }

    public function test_requesting_an_appointment_in_the_past_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'description' => 'Vidange.',
            'requested_at' => now()->subDay()->toIso8601String(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_at');
    }

    public function test_requesting_an_appointment_with_an_unapproved_garage_is_rejected(): void
    {
        $garage = Garage::factory()->for(User::factory()->garagiste())->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'description' => 'Vidange.',
            'requested_at' => now()->addDays(2)->toIso8601String(),
        ]);

        $response->assertNotFound();
    }

    public function test_requesting_an_appointment_with_a_service_from_another_garage_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $otherGarage = $this->approvedGarage();
        $service = RepairService::factory()->forGarage($otherGarage)->approved()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'repair_service_id' => $service->id,
            'requested_at' => now()->addDays(2)->toIso8601String(),
        ]);

        $response->assertNotFound();
    }

    public function test_requesting_an_appointment_with_an_unapproved_service_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $service = RepairService::factory()->forGarage($garage)->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/appointments', [
            'garage_id' => $garage->id,
            'repair_service_id' => $service->id,
            'requested_at' => now()->addDays(2)->toIso8601String(),
        ]);

        $response->assertNotFound();
    }

    public function test_an_automobiliste_can_list_its_own_appointments(): void
    {
        $user = User::factory()->create();
        Appointment::factory()->forGarage($this->approvedGarage())->for($user)->count(2)->create();
        Appointment::factory()->forGarage($this->approvedGarage())->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/appointments');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_automobiliste_cannot_view_anothers_appointment(): void
    {
        $appointment = Appointment::factory()->forGarage($this->approvedGarage())->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/mobile/appointments/{$appointment->id}")->assertNotFound();
    }

    public function test_an_automobiliste_can_cancel_a_pending_appointment(): void
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->forGarage($this->approvedGarage())->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/mobile/appointments/{$appointment->id}/cancel");

        $response->assertOk()->assertJsonPath('data.status', AppointmentStatus::Cancelled->value);
    }

    public function test_an_automobiliste_can_cancel_a_confirmed_appointment(): void
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->forGarage($this->approvedGarage())->for($user)->confirmed()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value);
    }

    public function test_an_automobiliste_cannot_cancel_a_completed_appointment(): void
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->forGarage($this->approvedGarage())->for($user)->completed()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/appointments/{$appointment->id}/cancel")->assertForbidden();
    }

    public function test_an_automobiliste_can_accept_a_reschedule_proposal(): void
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->forGarage($this->approvedGarage())->for($user)->rescheduled()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/mobile/appointments/{$appointment->id}/accept-reschedule");

        $response->assertOk()->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);
        $this->assertSame(
            $appointment->fresh()->confirmed_at->toIso8601String(),
            $appointment->proposed_at->toIso8601String(),
        );
    }

    public function test_accepting_a_reschedule_is_forbidden_when_the_appointment_is_still_pending(): void
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->forGarage($this->approvedGarage())->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/appointments/{$appointment->id}/accept-reschedule")->assertForbidden();
    }

    public function test_a_non_automobiliste_cannot_access_the_appointment_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/mobile/appointments')->assertForbidden();
    }
}
