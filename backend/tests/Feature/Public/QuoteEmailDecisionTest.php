<?php

namespace Tests\Feature\Public;

use App\Enums\QuoteStatus;
use App\Enums\QuoteVersionDecision;
use App\Mail\QuoteDecisionMail;
use App\Models\Garage;
use App\Models\Product;
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
 * explicite et tracée comme depuis l'app. Depuis v0.30 : un GET sur le lien
 * n'a aucun effet, seul un POST explicite décide ; signature relative.
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

    public function test_the_email_buttons_point_to_the_frontend_page_with_a_relative_signed_link(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://app.makecars.test']);
        $garage = $this->approvedGarage();
        $quote = Quote::factory()->forGarage($garage)->forClient($this->expressClient())->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->create();
        $version->lines()->create([
            'type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000,
        ]);
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/quotes/{$quote->id}/versions/{$version->id}/send")->assertOk();

        Mail::assertSent(QuoteDecisionMail::class, function (QuoteDecisionMail $mail) use ($quote, $version) {
            parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $query);

            return str_starts_with($mail->acceptUrl, 'https://app.makecars.test/devis/decision?')
                && $query['choix'] === 'accepter'
                && str_starts_with($query['link'], "/api/quotes/{$quote->id}/versions/{$version->id}/email-decision?")
                && str_contains($mail->rejectUrl, 'choix=refuser');
        });
    }

    /**
     * @return array{Quote, QuoteVersion, Product, User}
     */
    private function sentQuoteWithProductLine(): array
    {
        $garage = $this->approvedGarage();
        $client = $this->expressClient();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 10]);
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $version->lines()->create([
            'type' => 'product', 'product_id' => $product->id, 'label' => $product->name,
            'unit_price' => $product->price, 'quantity' => 3, 'line_total' => $product->price * 3,
        ]);

        return [$quote, $version, $product, $client];
    }

    private function signedPath(Quote $quote, QuoteVersion $version, int $days = 1): string
    {
        return URL::temporarySignedRoute(
            'quotes.email-decision',
            now()->addDays($days),
            ['quote' => $quote->id, 'version' => $version->id],
            absolute: false,
        );
    }

    /**
     * Cas d'un robot (messagerie, antivirus) qui ouvre le lien pour
     * l'analyser : la lecture ne décide rien (CLAUDE.md §5, ajout v0.30).
     */
    public function test_opening_the_link_changes_nothing(): void
    {
        [$quote, $version, $product] = $this->sentQuoteWithProductLine();

        $response = $this->getJson($this->signedPath($quote, $version));

        $response->assertOk()
            ->assertJsonPath('data.garage_name', $quote->garage->name)
            ->assertJsonPath('data.client_name', $quote->user->name)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.lines.0.quantity', 3)
            ->assertJsonPath('data.status', QuoteStatus::Sent->value)
            ->assertJsonPath('data.decision', null)
            ->assertJsonPath('data.is_decidable', true);
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertNull($version->fresh()->decision);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('push_notifications', 0);
    }

    public function test_posting_accept_accepts_the_quote_and_decrements_stock(): void
    {
        [$quote, $version, $product, $client] = $this->sentQuoteWithProductLine();

        $response = $this->postJson($this->signedPath($quote, $version), ['decision' => 'accept']);

        $response->assertOk()->assertJsonPath('data.decision', QuoteVersionDecision::Accepted->value);
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('quote_versions', ['id' => $version->id, 'decided_by' => $client->id]);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $quote->garage->user_id]);
    }

    public function test_posting_reject_rejects_the_quote_without_touching_stock(): void
    {
        [$quote, $version, $product, $client] = $this->sentQuoteWithProductLine();

        $response = $this->postJson($this->signedPath($quote, $version), ['decision' => 'reject']);

        $response->assertOk()->assertJsonPath('data.decision', QuoteVersionDecision::Rejected->value);
        $this->assertSame(QuoteStatus::Rejected, $quote->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('quote_versions', ['id' => $version->id, 'decided_by' => $client->id]);
    }

    public function test_an_invalid_decision_is_refused(): void
    {
        [$quote, $version] = $this->sentQuoteWithProductLine();

        $this->postJson($this->signedPath($quote, $version), ['decision' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
        $this->postJson($this->signedPath($quote, $version))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
        $this->assertNull($version->fresh()->decision);
    }

    public function test_an_expired_link_is_refused(): void
    {
        [$quote, $version] = $this->sentQuoteWithProductLine();
        $path = $this->signedPath($quote, $version, days: 7);

        $this->travel(8)->days();

        $this->getJson($path)->assertForbidden();
        $this->postJson($path, ['decision' => 'accept'])->assertForbidden();
        $this->assertNull($version->fresh()->decision);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        [$quote, $version] = $this->sentQuoteWithProductLine();
        $path = $this->signedPath($quote, $version);

        $this->postJson(str_replace('signature=', 'signature=0', $path), ['decision' => 'accept'])->assertForbidden();
        $this->getJson("/api/quotes/{$quote->id}/versions/{$version->id}/email-decision?signature=invalid")->assertForbidden();

        $otherQuote = Quote::factory()->forClient($this->expressClient2())->sent()->create();
        $this->postJson(str_replace("/quotes/{$quote->id}/", "/quotes/{$otherQuote->id}/", $path), ['decision' => 'accept'])
            ->assertForbidden();
        $this->assertNull($version->fresh()->decision);
    }

    /**
     * Signature relative : le frontend appelle l'API par un hôte qui peut
     * différer d'`APP_URL` (`127.0.0.1` contre `localhost`).
     */
    public function test_the_link_stays_valid_through_another_host(): void
    {
        config(['app.url' => 'http://localhost']);
        [$quote, $version] = $this->sentQuoteWithProductLine();
        $path = $this->signedPath($quote, $version);

        $this->getJson('http://127.0.0.1:8000'.$path)->assertOk();
        $this->postJson('http://127.0.0.1:8000'.$path, ['decision' => 'accept'])->assertOk();
    }

    public function test_an_already_decided_version_returns_a_conflict(): void
    {
        [$quote, $version] = $this->sentQuoteWithProductLine();
        $path = $this->signedPath($quote, $version);
        $this->postJson($path, ['decision' => 'reject'])->assertOk();

        $this->postJson($path, ['decision' => 'accept'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'quote_version_not_decidable');
        $this->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.is_decidable', false)
            ->assertJsonPath('data.decision', QuoteVersionDecision::Rejected->value);
    }

    public function test_a_stale_version_cannot_be_decided_via_the_signed_link(): void
    {
        $client = $this->expressClient();
        $quote = Quote::factory()->forClient($client)->create(['status' => QuoteStatus::Rejected]);
        $staleVersion = QuoteVersion::factory()->forQuote($quote, 1)->sent()->rejected()->create();
        QuoteVersion::factory()->forQuote($quote, 2)->sent()->create();

        $this->postJson($this->signedPath($quote, $staleVersion), ['decision' => 'accept'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'quote_version_not_decidable');
    }

    private function expressClient2(): User
    {
        return User::factory()->create(['is_express' => true, 'email' => 'other-express@example.com']);
    }
}
