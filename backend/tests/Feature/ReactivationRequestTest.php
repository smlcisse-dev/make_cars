<?php

namespace Tests\Feature;

use App\Enums\PushNotificationType;
use App\Enums\ReactivationRequestStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\ReactivationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Demande de réactivation d'un compte suspendu (CLAUDE.md §5, ajout v0.28) :
 * le professionnel demande, seul l'administrateur décide.
 */
class ReactivationRequestTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/garage/profile/reactivation-request';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function suspendedGaragiste(): ProfessionalRegistration
    {
        $registration = ProfessionalRegistration::factory()->approved()->suspended()->create();
        Garage::factory()->complete()->for($registration->user)->create();

        return $registration;
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    // --- Demande ----------------------------------------------------------------

    public function test_a_suspended_professional_requests_reactivation(): void
    {
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);

        $this->postJson(self::URL, ['message' => 'Pièces défectueuses retirées du catalogue.'])
            ->assertCreated()
            ->assertJsonPath('data.status', ReactivationRequestStatus::Pending->value)
            ->assertJsonPath('data.message', 'Pièces défectueuses retirées du catalogue.');

        $this->assertDatabaseHas('reactivation_requests', [
            'professional_registration_id' => $registration->id,
            'status' => ReactivationRequestStatus::Pending->value,
        ]);
        // Seul l'admin décide : le compte reste suspendu.
        $this->assertTrue($registration->fresh()->isSuspended());
    }

    public function test_the_market_space_has_the_same_endpoint(): void
    {
        $registration = ProfessionalRegistration::factory()->marketSpace()->approved()->suspended()->create();
        MarketSpaceAccount::factory()->complete()->for($registration->user)->create();
        Sanctum::actingAs($registration->user);

        $this->postJson('/api/market-space/profile/reactivation-request', ['message' => 'Corrigé.'])->assertCreated();
    }

    public function test_a_non_suspended_account_cannot_request_reactivation(): void
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();
        Garage::factory()->complete()->for($registration->user)->create();
        Sanctum::actingAs($registration->user);

        $this->postJson(self::URL, ['message' => 'Corrigé.'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'not_suspended');

        $this->assertDatabaseCount('reactivation_requests', 0);
    }

    public function test_a_second_request_is_refused_while_one_is_pending(): void
    {
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);

        $this->postJson(self::URL, ['message' => 'Corrigé.'])->assertCreated();
        $this->postJson(self::URL, ['message' => 'Encore moi.'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'reactivation_already_requested');

        $this->assertDatabaseCount('reactivation_requests', 1);
    }

    public function test_the_message_is_required_and_limited_to_2000_characters(): void
    {
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);

        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson(self::URL, ['message' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_the_session_exposes_the_latest_request(): void
    {
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.professional_registration.latest_reactivation_request', null)
            ->assertJsonPath('data.professional_registration.has_pending_reactivation_request', false);

        $this->postJson(self::URL, ['message' => 'Corrigé.'])->assertCreated();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.professional_registration.latest_reactivation_request.status', 'pending')
            ->assertJsonPath('data.professional_registration.latest_reactivation_request.message', 'Corrigé.')
            ->assertJsonPath('data.professional_registration.has_pending_reactivation_request', true)
            ->assertJsonMissingPath('data.professional_registration.reactivation_requests');
    }

    public function test_the_login_response_exposes_the_latest_request(): void
    {
        $registration = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($registration)->refused('Pas assez de preuves.')->create();

        $this->postJson('/api/auth/login', ['email' => $registration->user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.professional_registration.latest_reactivation_request.status', 'refused')
            ->assertJsonPath('data.user.professional_registration.latest_reactivation_request.response_reason', 'Pas assez de preuves.');
    }

    // --- Réactivation ----------------------------------------------------------------

    public function test_reactivating_the_account_accepts_the_pending_request(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = ReactivationRequest::factory()->for($registration)->create();
        $admin = $this->actingAdmin();

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.is_suspended', false)
            ->assertJsonPath('data.latest_reactivation_request.status', 'accepted')
            ->assertJsonPath('data.has_pending_reactivation_request', false);

        $request->refresh();
        $this->assertSame(ReactivationRequestStatus::Accepted, $request->status);
        $this->assertSame($admin->id, $request->decided_by);
        $this->assertNotNull($request->decided_at);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $registration->user_id, 'type' => PushNotificationType::AccountReactivated->value]);
    }

    public function test_reactivating_without_a_request_still_works(): void
    {
        $registration = $this->suspendedGaragiste();
        $this->actingAdmin();

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivate")->assertOk();
        $this->assertDatabaseCount('reactivation_requests', 0);
    }

    // --- Refus -----------------------------------------------------------------------

    public function test_refusing_a_request_keeps_the_account_suspended_and_notifies_the_professional(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = ReactivationRequest::factory()->for($registration)->create();
        $admin = $this->actingAdmin();

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivation-request/refuse", ['reason' => 'Les pièces sont toujours en vente.'])
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true)
            ->assertJsonPath('data.latest_reactivation_request.status', 'refused')
            ->assertJsonPath('data.latest_reactivation_request.response_reason', 'Les pièces sont toujours en vente.')
            ->assertJsonPath('data.has_pending_reactivation_request', false);

        $request->refresh();
        $this->assertSame(ReactivationRequestStatus::Refused, $request->status);
        $this->assertSame($admin->id, $request->decided_by);
        $this->assertTrue($registration->fresh()->isSuspended());
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $registration->user_id,
            'type' => PushNotificationType::ReactivationRequestRefused->value,
            'body' => 'Les pièces sont toujours en vente.',
        ]);
    }

    public function test_the_refusal_reason_is_required(): void
    {
        $registration = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($registration)->create();
        $this->actingAdmin();

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivation-request/refuse", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_refusing_without_a_pending_request_is_a_conflict(): void
    {
        $registration = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($registration)->refused()->create();
        $this->actingAdmin();

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivation-request/refuse", ['reason' => 'Non.'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'no_pending_reactivation_request');
    }

    public function test_only_an_admin_can_refuse_a_request(): void
    {
        $registration = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($registration)->create();
        Sanctum::actingAs($registration->user);

        $this->postJson("/api/admin/registrations/{$registration->id}/reactivation-request/refuse", ['reason' => 'Non.'])->assertForbidden();
    }

    public function test_a_new_request_is_possible_after_a_refusal(): void
    {
        $registration = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($registration)->refused()->create();
        Sanctum::actingAs($registration->user);

        $this->postJson(self::URL, ['message' => 'Cette fois, tout est corrigé.'])->assertCreated();

        $this->assertSame(2, $registration->reactivationRequests()->count());
    }

    // --- Historique ----------------------------------------------------------------------

    public function test_a_later_suspension_is_not_blocked_by_old_requests_and_history_is_kept(): void
    {
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);
        $this->postJson(self::URL, ['message' => 'Première demande.'])->assertCreated();

        $admin = $this->actingAdmin();
        $this->postJson("/api/admin/registrations/{$registration->id}/reactivate")->assertOk();
        $this->postJson("/api/admin/registrations/{$registration->id}/suspend", ['reason' => 'Nouvelles plaintes.'])->assertOk();

        Sanctum::actingAs($registration->user);
        $this->postJson(self::URL, ['message' => 'Deuxième demande.'])->assertCreated();

        Sanctum::actingAs($admin);
        $this->getJson("/api/admin/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.reactivation_requests')
            ->assertJsonPath('data.reactivation_requests.0.message', 'Deuxième demande.')
            ->assertJsonPath('data.reactivation_requests.0.status', 'pending')
            ->assertJsonPath('data.reactivation_requests.1.message', 'Première demande.')
            ->assertJsonPath('data.reactivation_requests.1.status', 'accepted')
            ->assertJsonPath('data.reactivation_requests.1.decided_by.id', $admin->id)
            ->assertJsonPath('data.has_pending_reactivation_request', true);
    }

    // --- Liste admin ---------------------------------------------------------------------

    public function test_the_admin_list_can_be_filtered_on_pending_reactivation_requests(): void
    {
        $withPending = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($withPending)->create();
        $withRefused = $this->suspendedGaragiste();
        ReactivationRequest::factory()->for($withRefused)->refused()->create();
        $this->suspendedGaragiste();
        $this->actingAdmin();

        $this->getJson('/api/admin/registrations?reactivation_requested=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withPending->id)
            ->assertJsonPath('data.0.has_pending_reactivation_request', true);

        $this->getJson('/api/admin/registrations')->assertOk()->assertJsonCount(3, 'data');
    }
}
