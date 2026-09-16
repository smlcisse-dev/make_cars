<?php

namespace Tests\Feature\Admin;

use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_suspend_an_approved_registration_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $registration = ProfessionalRegistration::factory()->approved()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/suspend", [
            'reason' => 'Plaintes répétées de clients pour pièces défectueuses.',
        ]);

        $response->assertOk()->assertJsonPath('data.is_suspended', true);
        $this->assertDatabaseHas('professional_registrations', [
            'id' => $registration->id,
            'suspension_reason' => 'Plaintes répétées de clients pour pièces défectueuses.',
            'suspended_by' => $admin->id,
        ]);
    }

    public function test_suspending_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->approved()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/suspend")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_a_registration_still_pending_review_cannot_be_suspended(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/suspend", [
            'reason' => 'Motif quelconque.',
        ])->assertForbidden();
    }

    public function test_an_already_suspended_registration_cannot_be_suspended_again(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->suspended()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/suspend", [
            'reason' => 'Motif quelconque.',
        ])->assertForbidden();
    }

    public function test_a_non_admin_cannot_suspend_a_registration(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());
        $registration = ProfessionalRegistration::factory()->approved()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/suspend", [
            'reason' => 'Motif quelconque.',
        ])->assertForbidden();
    }

    public function test_an_admin_can_reactivate_a_suspended_registration(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->suspended()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/reactivate");

        $response->assertOk()->assertJsonPath('data.is_suspended', false);
        $this->assertDatabaseHas('professional_registrations', [
            'id' => $registration->id,
            'suspension_reason' => null,
            'suspended_by' => null,
            'suspended_at' => null,
        ]);
    }

    public function test_reactivating_a_registration_that_is_not_suspended_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->approved()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivate")->assertForbidden();
    }

    public function test_suspending_a_registration_does_not_change_its_review_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->approved()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/suspend", [
            'reason' => 'Motif quelconque.',
        ])->assertOk();

        $this->assertSame('approved', $registration->fresh()->status->value);
    }
}
