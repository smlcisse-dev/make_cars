<?php

namespace Tests\Feature\Public;

use App\Enums\QuoteStatus;
use App\Enums\QuoteVersionDecision;
use App\Mail\QuoteDecisionMail;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Validation par email pour un client "compte express" sans app
 * (CLAUDE.md §5, ajout v0.9) : lien signé, à usage limité, action digitale
 * explicite et tracée comme depuis l'app.
 */
class QuoteEmailDecisionTest extends TestCase
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

        return Garage::factory()->complete()->for($registration->user)->create();
    }

    private function expressClient(): User
    {
        return User::factory()->create(['is_express' => true, 'email' => 'express@example.com']);
    }

    public function test_sending_a_quote_to_an_express_client_dispatches_a_decision_email(): void
    {
        Mail::fake();
        $garage = $this->approvedGarage();
        $client = $this->expressClient();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create();
        $version->lines()->create([
            'type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000,
        ]);
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}/send")->assertOk();

        Mail::assertSent(QuoteDecisionMail::class, function ($mail) use ($version) {
            $version = $version->fresh();

            return $mail->hasTo('express@example.com')
                && $mail->hasAttachment(
                    Attachment::fromStorageDisk($version->pdf_disk, $version->pdf_path)
                        ->as("devis-{$version->quote_id}-v{$version->version}.pdf")
                        ->withMime('application/pdf')
                );
        });
    }

    public function test_sending_a_quote_to_a_regular_client_does_not_dispatch_an_email(): void
    {
        Mail::fake();
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create();
        $version->lines()->create([
            'type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000,
        ]);
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}/send")->assertOk();

        Mail::assertNothingSent();
    }

    public function test_the_signed_link_accepts_the_quote(): void
    {
        $client = $this->expressClient();
        $quote = Quote::factory()->forClient($client)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $url = URL::temporarySignedRoute('quotes.email-decision.accept', now()->addDay(), ['quote' => $quote->id, 'version' => $version->id]);

        $response = $this->getJson($url);

        $response->assertOk()->assertJsonPath('data.decision', QuoteVersionDecision::Accepted->value);
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
        $this->assertDatabaseHas('quote_versions', ['id' => $version->id, 'decided_by' => $client->id]);
    }

    public function test_the_signed_link_rejects_the_quote(): void
    {
        $client = $this->expressClient();
        $quote = Quote::factory()->forClient($client)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $url = URL::temporarySignedRoute('quotes.email-decision.reject', now()->addDay(), ['quote' => $quote->id, 'version' => $version->id]);

        $response = $this->getJson($url);

        $response->assertOk()->assertJsonPath('data.decision', QuoteVersionDecision::Rejected->value);
    }

    public function test_an_unsigned_or_tampered_link_is_rejected(): void
    {
        $client = $this->expressClient();
        $quote = Quote::factory()->forClient($client)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();

        $this->getJson("/api/quotes/{$quote->id}/versions/{$version->id}/email-decision/accept?signature=invalid")
            ->assertForbidden();
    }

    public function test_a_stale_version_cannot_be_decided_via_the_signed_link(): void
    {
        $client = $this->expressClient();
        $quote = Quote::factory()->forClient($client)->create(['status' => QuoteStatus::Rejected]);
        $staleVersion = QuoteVersion::factory()->forQuote($quote, 1)->sent()->rejected()->create();
        QuoteVersion::factory()->forQuote($quote, 2)->sent()->create();
        $url = URL::temporarySignedRoute('quotes.email-decision.accept', now()->addDay(), ['quote' => $quote->id, 'version' => $staleVersion->id]);

        $this->getJson($url)->assertForbidden();
    }
}
