<?php

namespace Tests\Feature\Mobile;

use App\Models\Dispute;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Une réclamation n'est possible que sur une transaction terminée — un
 * devis facturé ou une commande payée, même principe que le module Avis
 * (CLAUDE.md §5, ajout v0.11).
 */
class DisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    private function approvedMarketSpaceAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->approved()->create([
            'user_id' => User::factory()->marketSpace(),
        ]);

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    public function test_an_automobiliste_can_dispute_an_invoiced_quote(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/quotes/{$quote->id}/dispute", [
            'reason' => 'La pièce remplacée est déjà défectueuse deux jours après.',
        ]);

        $response->assertCreated()->assertJsonPath('data.respondent_type', 'garage');
        $this->assertDatabaseHas('disputes', [
            'respondent_type' => Garage::class,
            'respondent_id' => $garage->id,
            'transaction_type' => Quote::class,
            'transaction_id' => $quote->id,
            'user_id' => $client->id,
            'status' => 'submitted',
        ]);
    }

    public function test_dispute_can_include_evidence_photos(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/quotes/{$quote->id}/dispute", [
            'reason' => 'Pièce défectueuse, photos à l\'appui.',
            'photos' => [UploadedFile::fake()->image('preuve1.jpg'), UploadedFile::fake()->image('preuve2.jpg')],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data.attachments');
    }

    public function test_disputing_a_quote_not_yet_invoiced_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->accepted()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/dispute", ['reason' => 'Motif quelconque'])
            ->assertForbidden();
    }

    public function test_disputing_another_clients_quote_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $quote = Quote::factory()->forGarage($garage)->invoiced()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/quotes/{$quote->id}/dispute", ['reason' => 'Motif quelconque'])
            ->assertNotFound();
    }

    public function test_an_automobiliste_can_dispute_a_paid_order_from_a_market_space(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $client = User::factory()->create();
        $order = Order::factory()->forMarketSpace($account)->forClient($client)->paid()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/orders/{$order->id}/dispute", [
            'reason' => 'Produit reçu différent de la commande.',
        ]);

        $response->assertCreated()->assertJsonPath('data.respondent_type', 'market_space');
    }

    public function test_disputing_an_unpaid_order_is_forbidden(): void
    {
        $client = User::factory()->create();
        $order = Order::factory()->forClient($client)->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/orders/{$order->id}/dispute", ['reason' => 'Motif quelconque'])
            ->assertForbidden();
    }

    public function test_reason_is_required(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/dispute", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_an_automobiliste_can_list_and_track_its_own_disputes(): void
    {
        $client = User::factory()->create();
        Dispute::factory()->forClient($client)->create();
        Dispute::factory()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/mobile/disputes')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_automobiliste_cannot_view_anothers_dispute(): void
    {
        $dispute = Dispute::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/mobile/disputes/{$dispute->id}")->assertNotFound();
    }
}
