<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_an_admin_can_list_pending_registrations(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        ProfessionalRegistration::factory()->count(2)->create();
        ProfessionalRegistration::factory()->approved()->create();

        $response = $this->getJson('/api/admin/registrations?status=pending');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_default_list_excludes_registrations_whose_profile_is_still_incomplete(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        ProfessionalRegistration::factory()->profileIncomplete()->create();
        ProfessionalRegistration::factory()->create();
        ProfessionalRegistration::factory()->rejected()->create();

        $this->getJson('/api/admin/registrations')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/registrations?status=profile_incomplete')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', RegistrationStatus::ProfileIncomplete->value)
            ->assertJsonPath('data.0.status_label', 'Profil à compléter');
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

    public function test_the_resource_keeps_structure_name_address_and_registration_number_read_from_the_profile(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->withProfile()->create(['business_registration_number' => 'RB/COT/24 B 12345']);
        $garage = $registration->user->garage;

        $this->getJson('/api/admin/registrations')
            ->assertOk()
            ->assertJsonPath('data.0.structure_name', $garage->name)
            ->assertJsonPath('data.0.address', $garage->address)
            ->assertJsonPath('data.0.business_registration_number', 'RB/COT/24 B 12345')
            ->assertJsonStructure(['data' => [['id', 'account_type', 'account_type_label', 'status', 'status_label', 'rejection_reason', 'reviewed_at', 'is_suspended', 'suspension_reason', 'suspended_at', 'documents', 'created_at']]]);
    }

    public function test_the_detail_embeds_the_full_profile_the_legal_info_and_the_decision_history(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $registration = ProfessionalRegistration::factory()->withProfile()->withBusinessRegistrationDocument()->create([
            'ifu' => '1234567890123',
            'npi' => '1234567890',
        ]);
        $registration->decisions()->create(['decision' => RegistrationStatus::Rejected, 'reason' => 'Photo floue.', 'decided_by' => $admin->id, 'decided_at' => now()->subDay()]);

        $this->getJson("/api/admin/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('data.ifu', '1234567890123')
            ->assertJsonPath('data.npi', '1234567890')
            ->assertJsonPath('data.legal_status.is_complete', true)
            ->assertJsonPath('data.submitted_at', $registration->submitted_at->toIso8601String())
            ->assertJsonPath('data.profile.name', $registration->user->garage->name)
            ->assertJsonCount(7, 'data.profile.opening_hours')
            ->assertJsonCount(1, 'data.profile.images')
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonPath('data.decisions.0.decision', RegistrationStatus::Rejected->value)
            ->assertJsonPath('data.decisions.0.reason', 'Photo floue.')
            ->assertJsonPath('data.decisions.0.decided_by.id', $admin->id);
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
        $registration = ProfessionalRegistration::factory()->withProfile()->create();

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

    public function test_approving_no_longer_creates_the_profile(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->withProfile()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();

        $this->assertSame(1, Garage::where('user_id', $registration->user_id)->count());
        $this->assertDatabaseCount('market_space_accounts', 0);
    }

    public function test_approving_records_the_decision_and_sends_the_approval_email(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $registration = ProfessionalRegistration::factory()->withProfile()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();

        $this->assertDatabaseHas('registration_decisions', [
            'professional_registration_id' => $registration->id,
            'decision' => RegistrationStatus::Approved->value,
            'reason' => null,
            'decided_by' => $admin->id,
        ]);
        Mail::assertSent(RegistrationApprovedMail::class, fn (RegistrationApprovedMail $mail) => $mail->hasTo($registration->user->email)
            && $mail->loginUrl === 'http://localhost:5173/login');
        Mail::assertNotSent(RegistrationRejectedMail::class);
    }

    public function test_an_admin_can_reject_a_pending_registration_with_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->withProfile()->create();

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

    public function test_rejecting_records_the_decision_and_sends_the_reason_by_email(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $registration = ProfessionalRegistration::factory()->marketSpace()->withProfile()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/reject", ['reason' => 'IFU incohérent.'])->assertOk();

        $this->assertDatabaseHas('registration_decisions', [
            'professional_registration_id' => $registration->id,
            'decision' => RegistrationStatus::Rejected->value,
            'reason' => 'IFU incohérent.',
            'decided_by' => $admin->id,
        ]);
        Mail::assertSent(RegistrationRejectedMail::class, fn (RegistrationRejectedMail $mail) => $mail->hasTo($registration->user->email)
            && $mail->reason === 'IFU incohérent.'
            && $mail->profileUrl === 'http://localhost:5173/market-space/profile');
    }

    public function test_rejecting_a_registration_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->create();

        $response = $this->postJson("/api/admin/registrations/{$registration->id}/reject");

        $response->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_only_a_pending_registration_can_be_approved_or_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach ([
            ProfessionalRegistration::factory()->approved()->create(),
            ProfessionalRegistration::factory()->rejected()->create(),
            ProfessionalRegistration::factory()->profileIncomplete()->create(),
        ] as $registration) {
            $this->postJson("/api/admin/registrations/{$registration->id}/approve")
                ->assertStatus(409)
                ->assertJsonPath('code', 'invalid_status');
            $this->postJson("/api/admin/registrations/{$registration->id}/reject", ['reason' => 'Motif.'])
                ->assertStatus(409)
                ->assertJsonPath('code', 'invalid_status');
        }

        $this->assertDatabaseCount('registration_decisions', 0);
        Mail::assertNothingSent();
    }

    public function test_the_decision_history_accumulates_across_resubmissions(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $registration = ProfessionalRegistration::factory()->withProfile()->withBusinessRegistrationDocument()->create();

        $this->postJson("/api/admin/registrations/{$registration->id}/reject", ['reason' => 'Photo floue.'])->assertOk();

        Sanctum::actingAs($registration->user);
        $this->postJson('/api/garage/profile/submit')->assertOk();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();

        $this->getJson("/api/admin/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.decisions')
            ->assertJsonPath('data.decisions.0.decision', RegistrationStatus::Approved->value)
            ->assertJsonPath('data.decisions.1.decision', RegistrationStatus::Rejected->value)
            ->assertJsonPath('data.decisions.1.reason', 'Photo floue.');
    }
}
