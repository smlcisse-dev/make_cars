<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_pending_registrations(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        ProfessionalRegistration::factory()->count(2)->create();
        ProfessionalRegistration::factory()->approved()->create();

        $response = $this->getJson('/api/admin/registrations?status=pending');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_registration_list_exposes_the_account_type_of_each_applicant(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        ProfessionalRegistration::factory()->create();
        ProfessionalRegistration::factory()->marketSpace()->create();

        $response = $this->getJson('/api/admin/registrations');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [AccountType::Garagiste->value, AccountType::MarketSpace->value],
            collect($response->json('data'))->pluck('account_type')->all(),
        );
    }

    public function test_a_non_admin_cannot_list_registrations(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $response = $this->getJson('/api/admin/registrations');

        $response->assertForbidden();
    }

    public function test_an_admin_can_approve_a_pending_registration(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $registration = ProfessionalRegistration::factory()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/approve");

        $response->assertOk()->assertJsonPath('data.status', RegistrationStatus::Approved->value);
        $response->assertJsonPath('data.account_type', $registration->user->role->value);
        $response->assertJsonPath('data.documents', fn ($documents) => is_array($documents));
        $this->assertDatabaseHas('professional_registrations', [
            'id' => $registration->id,
            'status' => RegistrationStatus::Approved->value,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_approving_a_garagiste_registration_auto_creates_its_garage_profile(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();

        $this->assertDatabaseHas('garages', [
            'user_id' => $registration->user_id,
            'name' => $registration->structure_name,
            'address' => $registration->address,
        ]);
    }

    public function test_approving_a_market_space_registration_does_not_create_a_garage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->marketSpace()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();

        $this->assertDatabaseMissing('garages', ['user_id' => $registration->user_id]);
        $this->assertSame(AccountType::MarketSpace, $registration->user->fresh()->role);
    }

    public function test_approving_a_market_space_registration_auto_creates_its_account(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->marketSpace()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();

        $this->assertDatabaseHas('market_space_accounts', [
            'user_id' => $registration->user_id,
            'name' => $registration->structure_name,
            'address' => $registration->address,
        ]);
    }

    public function test_an_admin_can_reject_a_pending_registration_with_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/reject", [
            'reason' => 'Registre de commerce illisible.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', RegistrationStatus::Rejected->value);
        $response->assertJsonPath('data.account_type', $registration->user->role->value);
        $response->assertJsonPath('data.documents', fn ($documents) => is_array($documents));
        $this->assertDatabaseHas('professional_registrations', [
            'id' => $registration->id,
            'status' => RegistrationStatus::Rejected->value,
            'rejection_reason' => 'Registre de commerce illisible.',
        ]);
    }

    public function test_rejecting_a_registration_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/reject");

        $response->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_an_already_approved_registration_cannot_be_reviewed_again(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->approved()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/approve");

        $response->assertForbidden();
    }
}
