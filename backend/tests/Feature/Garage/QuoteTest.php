<?php

namespace Tests\Feature\Garage;

use App\Enums\AppointmentStatus;
use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->complete()->for($registration->user)->create();
    }

    private function confirmedAppointment(Garage $garage): Appointment
    {
        return Appointment::factory()->forGarage($garage)->confirmed()->create();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_garagiste_can_create_a_draft_quote_with_mixed_lines(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $service = RepairService::factory()->forGarage($garage)->approved()->create(['price' => 15000]);
        $product = Product::factory()->forGarage($garage)->approved()->create(['price' => 5000, 'stock_quantity' => 10]);
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/appointments/{$appointment->id}/quote", [
            'lines' => [
                ['type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1],
                ['type' => 'service', 'repair_service_id' => $service->id, 'quantity' => 1],
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', QuoteStatus::Draft->value)
            ->assertJsonPath('data.client.id', $appointment->user_id);
        $this->assertDatabaseHas('quotes', ['appointment_id' => $appointment->id, 'status' => QuoteStatus::Draft->value]);
        $this->assertDatabaseCount('quote_lines', 3);
        $this->assertDatabaseHas('quote_lines', ['type' => 'product', 'unit_price' => 5000, 'quantity' => 2, 'line_total' => 10000]);
    }

    public function test_line_price_is_pulled_from_the_catalog_not_client_supplied(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $service = RepairService::factory()->forGarage($garage)->approved()->create(['price' => 15000]);
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/quote", [
            'lines' => [
                ['type' => 'service', 'repair_service_id' => $service->id, 'quantity' => 1, 'unit_price' => 1],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('quote_lines', ['repair_service_id' => $service->id, 'unit_price' => 15000]);
    }

    public function test_quoting_more_than_available_stock_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 1]);
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/quote", [
            'lines' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 5],
            ],
        ])->assertUnprocessable();
    }

    public function test_creating_a_quote_requires_a_confirmed_appointment(): void
    {
        $garage = $this->approvedGarage();
        $appointment = Appointment::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/quote", [
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1000, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_only_one_quote_per_appointment(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        Quote::factory()->forAppointment($appointment)->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/appointments/{$appointment->id}/quote", [
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1000, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_a_garagiste_can_edit_draft_lines_before_sending(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}", [
            'lines' => [['type' => 'diagnosis_fee', 'label' => 'Diag', 'unit_price' => 3000, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quote_lines', ['quote_version_id' => $version->id, 'unit_price' => 3000]);
    }

    public function test_a_garagiste_can_send_a_draft_version_which_notifies_the_client_via_chat(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create();
        $version->lines()->create([
            'type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000,
        ]);
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}/send");

        $response->assertOk()->assertJsonPath('data.is_sent', true);
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertNotNull($version->fresh()->pdf_path);
        $this->assertDatabaseHas('messages', [
            'attachment_type' => Message::ATTACHMENT_QUOTE_PDF,
            'quote_version_id' => $version->id,
            'sender_id' => null,
        ]);
        $this->assertDatabaseHas('conversations', ['sellable_type' => $garage->getMorphClass(), 'sellable_id' => $garage->id, 'user_id' => $appointment->user_id]);
    }

    public function test_sending_an_already_sent_version_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}/send")->assertForbidden();
    }

    public function test_editing_lines_after_sending_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        Sanctum::actingAs($garage->user);

        $this->putJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}", [
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_a_garagiste_can_renegotiate_after_a_rejection(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->create(['status' => QuoteStatus::Rejected]);
        QuoteVersion::factory()->forQuote($quote, 1)->sent()->rejected()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/quotes/{$quote->id}/versions", [
            'lines' => [['type' => 'diagnosis_fee', 'label' => 'Diag', 'unit_price' => 1500, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.version', 2);
    }

    public function test_renegotiating_without_a_prior_rejection_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/versions", [
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_a_garagiste_can_start_the_prestation_after_acceptance(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->accepted()->create();
        QuoteVersion::factory()->forQuote($quote, 1)->sent()->accepted()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::InProgress->value)
            ->assertJsonPath('data.client.id', $quote->user_id)
            ->assertJsonCount(1, 'data.versions');
    }

    public function test_starting_without_acceptance_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/start")->assertForbidden();
    }

    public function test_marking_paid_generates_an_invoice_and_completes_the_appointment_with_service(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->create(['status' => QuoteStatus::InProgress]);
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->accepted()->create();
        $version->lines()->create([
            'type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000,
        ]);
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/quotes/{$quote->id}/mark-paid");

        $response->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::Invoiced->value)
            ->assertJsonPath('data.client.id', $quote->user_id)
            ->assertJsonCount(2, 'data.versions');
        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
        $this->assertTrue($appointment->fresh()->wasCompletedWithService());
        $this->assertDatabaseHas('quote_versions', ['quote_id' => $quote->id, 'version' => 2, 'document_type' => 'invoice']);
        $this->assertDatabaseHas('quote_lines', ['quote_version_id' => $quote->versions()->where('version', 2)->first()->id, 'unit_price' => 2000]);
    }

    public function test_marking_paid_without_being_in_progress_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->accepted()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/mark-paid")->assertForbidden();
    }

    public function test_a_garagiste_can_abandon_after_a_rejection_completing_the_appointment_without_service(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->create(['status' => QuoteStatus::Rejected]);
        QuoteVersion::factory()->forQuote($quote, 1)->sent()->rejected()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/quotes/{$quote->id}/abandon");

        $response->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::Abandoned->value)
            ->assertJsonPath('data.client.id', $quote->user_id)
            ->assertJsonCount(1, 'data.versions');
        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
        $this->assertFalse($appointment->fresh()->wasCompletedWithService());
    }

    public function test_abandoning_without_a_rejection_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/abandon")->assertForbidden();
    }

    public function test_a_garagiste_can_download_the_pdf_of_a_sent_version(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create([
            'pdf_disk' => 'local',
            'pdf_path' => 'quotes/test.pdf',
            'sent_at' => now(),
        ]);
        Storage::disk('local')->put('quotes/test.pdf', '%PDF-1.7 fake');
        Sanctum::actingAs($garage->user);

        $this->get("/api/garage/quotes/{$quote->id}/versions/{$version->id}/pdf")->assertOk();
    }

    public function test_a_garagiste_cannot_manage_another_garages_quote(): void
    {
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointment($garage);
        $quote = Quote::factory()->forAppointment($appointment)->create();
        $otherGarage = $this->approvedGarage();
        Sanctum::actingAs($otherGarage->user);

        $this->getJson("/api/garage/appointments/{$appointment->id}/quote")->assertNotFound();
        $this->postJson("/api/garage/quotes/{$quote->id}/start")->assertNotFound();
    }

    /**
     * Un devis est nécessaire dès qu'il y a une prestation, avec ou sans RDV
     * préalable (CLAUDE.md §5, ajout v0.9) : un client walk-in sans RDV.
     */
    public function test_a_garagiste_can_create_a_quote_directly_without_an_appointment(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/quotes', [
            'client_id' => $client->id,
            'lines' => [
                ['type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.appointment_id', null)
            ->assertJsonPath('data.client.id', $client->id);
        $this->assertDatabaseHas('quotes', [
            'garage_id' => $garage->id,
            'user_id' => $client->id,
            'appointment_id' => null,
        ]);
    }

    public function test_creating_a_direct_quote_without_a_client_id_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs($garage->user);

        $this->postJson('/api/garage/quotes', [
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1000, 'quantity' => 1]],
        ])->assertUnprocessable();
    }

    public function test_creating_a_direct_quote_for_a_non_automobiliste_client_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $professional = User::factory()->garagiste()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson('/api/garage/quotes', [
            'client_id' => $professional->id,
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1000, 'quantity' => 1]],
        ])->assertNotFound();
    }

    public function test_a_garagiste_can_list_and_view_a_walk_in_quote_without_an_appointment(): void
    {
        $garage = $this->approvedGarage();
        $quote = Quote::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/quotes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/garage/quotes/{$quote->id}")->assertOk()->assertJsonPath('data.id', $quote->id);
    }

    public function test_the_quote_list_is_paginated_with_meta(): void
    {
        $garage = $this->approvedGarage();
        Quote::factory()->forGarage($garage)->count(16)->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/quotes')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_show_exposes_the_client_and_versions(): void
    {
        $garage = $this->approvedGarage();
        $quote = Quote::factory()->forGarage($garage)->create();
        QuoteVersion::factory()->forQuote($quote, 1)->create();
        Sanctum::actingAs($garage->user);

        $this->getJson("/api/garage/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('data.client.id', $quote->user_id)
            ->assertJsonCount(1, 'data.versions');
    }

    public function test_full_cycle_decrements_stock_once_at_acceptance_and_copies_lines_to_the_invoice(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create(['price' => 5000, 'stock_quantity' => 10]);
        Sanctum::actingAs($garage->user);

        $created = $this->postJson('/api/garage/quotes', [
            'client_id' => $client->id,
            'lines' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated();
        $quoteId = $created->json('data.id');
        $versionId = $created->json('data.versions.0.id');
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->postJson("/api/garage/quotes/{$quoteId}/versions/{$versionId}/send")->assertOk();
        $this->assertSame(10, $product->fresh()->stock_quantity);

        Sanctum::actingAs($client);
        $this->postJson("/api/mobile/quotes/{$quoteId}/versions/{$versionId}/accept")->assertOk();
        $this->assertSame(7, $product->fresh()->stock_quantity);

        Sanctum::actingAs($garage->user);
        $this->postJson("/api/garage/quotes/{$quoteId}/start")->assertOk();
        $this->postJson("/api/garage/quotes/{$quoteId}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::Invoiced->value);

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $invoice = Quote::findOrFail($quoteId)->versions()->where('document_type', 'invoice')->firstOrFail();
        $this->assertDatabaseHas('quote_lines', [
            'quote_version_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => 3, 'unit_price' => 5000,
        ]);
    }

    public function test_refusal_then_new_version_can_be_sent(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        Sanctum::actingAs($garage->user);

        $created = $this->postJson('/api/garage/quotes', [
            'client_id' => $client->id,
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 5000, 'quantity' => 1]],
        ])->assertCreated();
        $quoteId = $created->json('data.id');
        $v1 = $created->json('data.versions.0.id');
        $this->postJson("/api/garage/quotes/{$quoteId}/versions/{$v1}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->postJson("/api/mobile/quotes/{$quoteId}/versions/{$v1}/reject")->assertOk();

        Sanctum::actingAs($garage->user);
        $v2 = $this->postJson("/api/garage/quotes/{$quoteId}/versions", [
            'lines' => [['type' => 'diagnosis_fee', 'unit_price' => 3000, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/garage/quotes/{$quoteId}/versions/{$v2}/send")
            ->assertOk()
            ->assertJsonPath('data.version', 2);
        $this->assertSame(QuoteStatus::Negotiating, Quote::findOrFail($quoteId)->status);
    }

    public function test_a_garagiste_cannot_touch_another_garages_quote_on_any_action(): void
    {
        $garage = $this->approvedGarage();
        $quote = Quote::factory()->forGarage($garage)->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create(['pdf_disk' => 'local', 'pdf_path' => 'quotes/x.pdf']);
        $other = $this->approvedGarage();
        $lines = ['lines' => [['type' => 'diagnosis_fee', 'unit_price' => 1, 'quantity' => 1]]];
        Sanctum::actingAs($other->user);

        $this->getJson("/api/garage/quotes/{$quote->id}")->assertNotFound();
        $this->putJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}", $lines)->assertNotFound();
        $this->postJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}/send")->assertNotFound();
        $this->postJson("/api/garage/quotes/{$quote->id}/versions", $lines)->assertNotFound();
        $this->postJson("/api/garage/quotes/{$quote->id}/start")->assertNotFound();
        $this->postJson("/api/garage/quotes/{$quote->id}/mark-paid")->assertNotFound();
        $this->postJson("/api/garage/quotes/{$quote->id}/abandon")->assertNotFound();
        $this->get("/api/garage/quotes/{$quote->id}/versions/{$version->id}/pdf")->assertNotFound();
        $this->getJson('/api/garage/quotes')->assertJsonCount(0, 'data');
    }
}
