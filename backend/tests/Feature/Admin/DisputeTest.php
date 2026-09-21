<?php

namespace Tests\Feature\Admin;

use App\Enums\DisputeResolutionAction;
use App\Mail\DisputeDecisionMail;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Instruction et décision admin d'une réclamation — droit de réponse au
 * professionnel, accès à tout l'historique lié, décision motivée et tracée,
 * aucune sanction automatique (CLAUDE.md §5, ajout v0.11).
 */
class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_an_admin_can_list_and_filter_disputes_by_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Dispute::factory()->count(2)->create();
        Dispute::factory()->resolvedRejected()->create();

        $this->getJson('/api/admin/disputes?status=resolved_rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_admin_can_view_a_disputes_full_context(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $dispute = Dispute::factory()->forGarage($garage)->forClient($client)->create();
        $conversation = Conversation::factory()->between($garage, $client)->create();
        Review::factory()->forGarage($garage)->create();

        $response = $this->getJson("/api/admin/disputes/{$dispute->id}");

        $response->assertOk()
            ->assertJsonPath('data.dispute.id', $dispute->id)
            ->assertJsonPath('data.dispute.respondent.name', $garage->name)
            ->assertJsonPath('data.dispute.transaction.id', $dispute->transaction_id)
            ->assertJsonPath('data.conversation.id', $conversation->id)
            ->assertJsonCount(1, 'data.reviews');
    }

    public function test_an_admin_can_request_a_response_from_the_professional(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $dispute = Dispute::factory()->create();

        $response = $this->postJson("/api/admin/disputes/{$dispute->id}/request-response", [
            'message' => 'Merci de nous expliquer ce qui s\'est passé.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'under_review');
        $response->assertJsonPath('data.attachments', fn ($attachments) => is_array($attachments));
        $response->assertJsonPath('data.messages', fn ($messages) => is_array($messages));
        $response->assertJsonPath('data.respondent.name', $dispute->respondent->name);
        $response->assertJsonPath('data.client.id', $dispute->user_id);
        $this->assertDatabaseHas('dispute_messages', [
            'dispute_id' => $dispute->id,
            'author_id' => $admin->id,
        ]);
    }

    public function test_requesting_a_response_twice_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->underReview()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/request-response")->assertForbidden();
    }

    public function test_an_admin_can_post_several_messages_in_the_exchange_space(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $dispute = Dispute::factory()->underReview()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/messages", ['body' => 'Une relance de l\'admin.'])
            ->assertCreated();

        $this->assertDatabaseHas('dispute_messages', [
            'dispute_id' => $dispute->id,
            'author_id' => $admin->id,
            'body' => 'Une relance de l\'admin.',
        ]);
    }

    public function test_an_admin_cannot_message_an_already_decided_dispute(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->resolvedRejected()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/messages", ['body' => 'Trop tard.'])
            ->assertForbidden();
    }

    public function test_an_admin_can_reject_a_dispute_with_a_reason_and_notifies_the_client(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $client = User::factory()->create(['email' => 'client@example.com']);
        $dispute = Dispute::factory()->forClient($client)->create();

        $response = $this->postJson("/api/admin/disputes/{$dispute->id}/reject", [
            'reason' => 'Aucune preuve suffisante fournie.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'resolved_rejected');
        $response->assertJsonPath('data.attachments', fn ($attachments) => is_array($attachments));
        $response->assertJsonPath('data.messages', fn ($messages) => is_array($messages));
        $response->assertJsonPath('data.respondent.name', $dispute->respondent->name);
        $response->assertJsonPath('data.client.id', $dispute->user_id);
        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => 'resolved_rejected',
            'resolution_reason' => 'Aucune preuve suffisante fournie.',
            'decided_by' => $admin->id,
        ]);
        Mail::assertSent(DisputeDecisionMail::class);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_an_admin_can_resolve_a_dispute_as_founded_with_a_warning_only(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->admin()->create());
        $garage = $this->approvedGarage();
        $dispute = Dispute::factory()->forGarage($garage)->create();

        $response = $this->postJson("/api/admin/disputes/{$dispute->id}/resolve", [
            'reason' => 'Prestation mal réalisée, confirmé par les photos.',
            'action' => DisputeResolutionAction::Warning->value,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'resolved_founded');
        $response->assertJsonPath('data.attachments', fn ($attachments) => is_array($attachments));
        $response->assertJsonPath('data.messages', fn ($messages) => is_array($messages));
        $response->assertJsonPath('data.respondent.name', $dispute->respondent->name);
        $response->assertJsonPath('data.client.id', $dispute->user_id);
        $this->assertSame('approved', $garage->user->professionalRegistration->fresh()->status->value);
        $this->assertFalse($garage->user->professionalRegistration->fresh()->isSuspended());
        Mail::assertSent(DisputeDecisionMail::class);
    }

    public function test_an_admin_can_resolve_a_dispute_as_founded_and_suspend_the_account(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $garage = $this->approvedGarage();
        $dispute = Dispute::factory()->forGarage($garage)->create();

        $response = $this->postJson("/api/admin/disputes/{$dispute->id}/resolve", [
            'reason' => 'Pièces de mauvaise qualité confirmées, cas grave.',
            'action' => DisputeResolutionAction::Suspension->value,
        ]);

        $response->assertOk()->assertJsonPath('data.resolution_action', 'suspension');
        $registration = $garage->user->professionalRegistration->fresh();
        $this->assertTrue($registration->isSuspended());
        $this->assertSame('Pièces de mauvaise qualité confirmées, cas grave.', $registration->suspension_reason);
    }

    public function test_resolving_requires_a_reason_and_a_valid_action(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/resolve", ['action' => 'not-a-real-action'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason', 'action']);
    }

    public function test_a_dispute_cannot_be_decided_twice(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->resolvedRejected()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/reject", ['reason' => 'Motif quelconque'])
            ->assertForbidden();
    }

    public function test_an_admin_can_close_a_resolved_dispute(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->resolvedRejected()->create();

        $response = $this->postJson("/api/admin/disputes/{$dispute->id}/close");

        $response->assertOk()->assertJsonPath('data.status', 'closed');
        $response->assertJsonPath('data.attachments', fn ($attachments) => is_array($attachments));
        $response->assertJsonPath('data.messages', fn ($messages) => is_array($messages));
        $response->assertJsonPath('data.respondent.name', $dispute->respondent->name);
        $response->assertJsonPath('data.client.id', $dispute->user_id);
    }

    public function test_a_dispute_not_yet_resolved_cannot_be_closed(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dispute = Dispute::factory()->create();

        $this->postJson("/api/admin/disputes/{$dispute->id}/close")->assertForbidden();
    }

    public function test_a_non_admin_cannot_access_dispute_supervision(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/disputes')->assertForbidden();
    }
}
