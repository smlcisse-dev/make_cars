<?php

namespace Tests\Feature\Mobile;

use App\Enums\QuoteStatus;
use App\Enums\QuoteVersionDecision;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteTest extends TestCase
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

    private function confirmedAppointmentWithClient(Garage $garage, User $client): Appointment
    {
        return Appointment::factory()->forGarage($garage)->for($client)->confirmed()->create();
    }

    public function test_an_automobiliste_can_view_its_quote(): void
    {
        $client = User::factory()->create();
        $appointment = $this->confirmedAppointmentWithClient($this->approvedGarage(), $client);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        Sanctum::actingAs($client);

        $response = $this->getJson("/api/mobile/appointments/{$appointment->id}/quote");

        $response->assertOk()->assertJsonPath('data.id', $quote->id);
    }

    public function test_an_automobiliste_cannot_view_anothers_quote(): void
    {
        $appointment = $this->confirmedAppointmentWithClient($this->approvedGarage(), User::factory()->create());
        Quote::factory()->forAppointment($appointment)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/mobile/appointments/{$appointment->id}/quote")->assertNotFound();
    }

    public function test_an_automobiliste_can_accept_the_current_sent_version_and_stock_is_decremented(): void
    {
        $client = User::factory()->create();
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointmentWithClient($garage, $client);
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 10]);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $version->lines()->create([
            'type' => 'product', 'product_id' => $product->id, 'label' => $product->name,
            'unit_price' => $product->price, 'quantity' => 3, 'line_total' => $product->price * 3,
        ]);
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/quotes/{$quote->id}/versions/{$version->id}/accept");

        $response->assertOk()->assertJsonPath('data.decision', QuoteVersionDecision::Accepted->value);
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('quote_versions', ['id' => $version->id, 'decided_by' => $client->id]);
    }

    public function test_accepting_does_not_touch_stock_for_non_product_lines(): void
    {
        $client = User::factory()->create();
        $garage = $this->approvedGarage();
        $appointment = $this->confirmedAppointmentWithClient($garage, $client);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $version->lines()->create([
            'type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000,
        ]);
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/versions/{$version->id}/accept")->assertOk();
    }

    public function test_an_automobiliste_can_reject_the_current_sent_version(): void
    {
        $client = User::factory()->create();
        $appointment = $this->confirmedAppointmentWithClient($this->approvedGarage(), $client);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/quotes/{$quote->id}/versions/{$version->id}/reject");

        $response->assertOk()->assertJsonPath('data.decision', QuoteVersionDecision::Rejected->value);
        $this->assertSame(QuoteStatus::Rejected, $quote->fresh()->status);
    }

    public function test_accepting_a_draft_version_that_was_never_sent_is_forbidden(): void
    {
        $client = User::factory()->create();
        $appointment = $this->confirmedAppointmentWithClient($this->approvedGarage(), $client);
        $quote = Quote::factory()->forAppointment($appointment)->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/versions/{$version->id}/accept")->assertForbidden();
    }

    public function test_accepting_a_stale_version_is_forbidden(): void
    {
        $client = User::factory()->create();
        $appointment = $this->confirmedAppointmentWithClient($this->approvedGarage(), $client);
        $quote = Quote::factory()->forAppointment($appointment)->create(['status' => QuoteStatus::Rejected]);
        $staleVersion = QuoteVersion::factory()->forQuote($quote, 1)->sent()->rejected()->create();
        QuoteVersion::factory()->forQuote($quote, 2)->sent()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/versions/{$staleVersion->id}/accept")->assertForbidden();
    }

    public function test_an_automobiliste_can_download_the_pdf(): void
    {
        $client = User::factory()->create();
        $appointment = $this->confirmedAppointmentWithClient($this->approvedGarage(), $client);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create([
            'pdf_disk' => 'local', 'pdf_path' => 'quotes/test.pdf', 'sent_at' => now(),
        ]);
        Storage::disk('local')->put('quotes/test.pdf', '%PDF-1.7 fake');
        Sanctum::actingAs($client);

        $this->get("/api/mobile/quotes/{$quote->id}/versions/{$version->id}/pdf")->assertOk();
    }

    /**
     * Un devis sans RDV associé (client walk-in — CLAUDE.md §5, ajout v0.9)
     * reste consultable directement par son id.
     */
    public function test_an_automobiliste_can_list_and_view_a_walk_in_quote_without_an_appointment(): void
    {
        $client = User::factory()->create();
        $quote = Quote::factory()->forClient($client)->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/mobile/quotes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/mobile/quotes/{$quote->id}")->assertOk()->assertJsonPath('data.appointment_id', null);
    }

    public function test_an_automobiliste_cannot_view_anothers_walk_in_quote(): void
    {
        $quote = Quote::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/mobile/quotes/{$quote->id}")->assertNotFound();
    }
}
