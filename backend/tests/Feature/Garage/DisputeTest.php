<?php

namespace Tests\Feature\Garage;

use App\Models\Dispute;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le garage consulte les réclamations le concernant et peut y répondre —
 * jamais de décision de son côté, réservée à l'admin (CLAUDE.md §5, ajout
 * v0.11).
 */
class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->complete()->for($registration->user)->create();
    }

    public function test_a_garagiste_can_list_disputes_concerning_it(): void
    {
        $garage = $this->approvedGarage();
        Dispute::factory()->forGarage($garage)->create();
        Dispute::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/disputes')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_disputes_list_exposes_the_client(): void
    {
        $garage = $this->approvedGarage();
        $dispute = Dispute::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/disputes')
            ->assertOk()
            ->assertJsonPath('data.0.client.id', $dispute->user_id);
    }

    public function test_a_garagiste_can_filter_its_disputes_by_status(): void
    {
        $garage = $this->approvedGarage();
        $submitted = Dispute::factory()->forGarage($garage)->create();
        Dispute::factory()->forGarage($garage)->resolvedRejected()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/disputes?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted->id);
        $this->getJson('/api/garage/disputes')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_garagiste_can_respond_to_a_dispute(): void
    {
        $garage = $this->approvedGarage();
        $dispute = Dispute::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/disputes/{$dispute->id}/respond", [
            'body' => 'La pièce a été posée neuve, voici la facture fournisseur.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dispute_messages', [
            'dispute_id' => $dispute->id,
            'author_id' => $garage->user_id,
        ]);
        $this->assertSame('under_review', $dispute->fresh()->status->value);
    }

    public function test_a_garagiste_cannot_respond_to_an_already_decided_dispute(): void
    {
        $garage = $this->approvedGarage();
        $dispute = Dispute::factory()->forGarage($garage)->resolvedRejected()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/disputes/{$dispute->id}/respond", ['body' => 'Réponse tardive'])
            ->assertForbidden();
    }

    public function test_a_garagiste_cannot_access_another_garages_dispute(): void
    {
        $dispute = Dispute::factory()->create();
        $otherGarage = $this->approvedGarage();
        Sanctum::actingAs($otherGarage->user);

        $this->getJson("/api/garage/disputes/{$dispute->id}")->assertNotFound();
    }
}
