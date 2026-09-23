<?php

namespace Tests\Feature;

use App\Enums\RegistrationDocumentType;
use App\Enums\RegistrationStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Profil et informations légales avant la validation admin (CLAUDE.md §5,
 * ajout v0.26) : informations légales, soumission, verrous.
 */
class ProfessionalDossierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    /**
     * Garagiste connecté avec un profil complet et un dossier au statut
     * demandé (informations légales remplies sauf indication contraire).
     */
    private function actingGaragiste(RegistrationStatus $status = RegistrationStatus::ProfileIncomplete, bool $withLegal = true, bool $completeProfile = true): ProfessionalRegistration
    {
        $user = User::factory()->garagiste()->create();
        $registration = ProfessionalRegistration::factory()->for($user)->create([
            'status' => $status,
            'business_registration_number' => $withLegal ? 'RB/COT/24 B 12345' : null,
            'ifu' => $withLegal ? '1234567890123' : null,
            'npi' => $withLegal ? '1234567890' : null,
            'submitted_at' => $status === RegistrationStatus::Pending ? now() : null,
            'rejection_reason' => $status === RegistrationStatus::Rejected ? 'Photo floue.' : null,
        ]);

        if ($withLegal) {
            $registration->documents()->create(['type' => RegistrationDocumentType::BusinessRegistration, 'disk' => 'local', 'path' => 'registration-documents/rccm.pdf']);
            $registration->documents()->create(['type' => RegistrationDocumentType::IdentityCertificate, 'disk' => 'local', 'path' => 'registration-documents/cip.pdf']);
        }

        ($completeProfile ? Garage::factory()->complete() : Garage::factory())->for($user)->create();
        Sanctum::actingAs($user);

        return $registration;
    }

    /**
     * @return array<string, string>
     */
    private function legalPayload(array $overrides = []): array
    {
        return ['business_registration_number' => 'RB/COT/24 B 12345', 'ifu' => '1234567890123', 'npi' => '1234567890', ...$overrides];
    }

    // --- Soumission -------------------------------------------------------

    public function test_a_complete_dossier_can_be_submitted(): void
    {
        $registration = $this->actingGaragiste();

        $this->postJson('/api/garage/profile/submit')
            ->assertOk()
            ->assertJsonPath('meta.registration.status', RegistrationStatus::Pending->value)
            ->assertJsonPath('meta.legal_status.is_complete', true);

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Pending, $registration->status);
        $this->assertNotNull($registration->submitted_at);
    }

    public function test_submission_is_refused_while_the_profile_is_incomplete(): void
    {
        $this->actingGaragiste(completeProfile: false);

        $this->postJson('/api/garage/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'registration_incomplete')
            ->assertJsonPath('missing_fields', fn ($fields) => in_array('neighborhood', $fields, true))
            ->assertJsonPath('missing_legal_fields', []);
    }

    public function test_submission_is_refused_while_legal_info_is_missing(): void
    {
        $this->actingGaragiste(withLegal: false);

        $this->postJson('/api/garage/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('missing_fields', [])
            ->assertJsonPath('missing_legal_fields', ['business_registration_number', 'business_registration_document', 'ifu', 'npi', 'identity_certificate_document']);
    }

    public function test_submission_is_refused_from_a_pending_or_approved_status(): void
    {
        $this->actingGaragiste(RegistrationStatus::Pending);
        $this->postJson('/api/garage/profile/submit')->assertStatus(409)->assertJsonPath('code', 'invalid_status');

        $this->actingGaragiste(RegistrationStatus::Approved);
        $this->postJson('/api/garage/profile/submit')->assertStatus(409)->assertJsonPath('code', 'invalid_status');
    }

    public function test_a_rejected_dossier_can_be_corrected_and_submitted_again_keeping_the_previous_reason(): void
    {
        $registration = $this->actingGaragiste(RegistrationStatus::Rejected);

        $this->putJson('/api/garage/profile/legal', $this->legalPayload(['ifu' => '9876543210987']))->assertOk();
        $this->postJson('/api/garage/profile/submit')->assertOk();

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Pending, $registration->status);
        $this->assertSame('9876543210987', $registration->ifu);
        $this->assertSame('Photo floue.', $registration->rejection_reason);
    }

    public function test_a_market_space_dossier_goes_through_the_same_submission(): void
    {
        $user = User::factory()->marketSpace()->create();
        $registration = ProfessionalRegistration::factory()->for($user)->profileIncomplete()->create();
        MarketSpaceAccount::factory()->complete()->for($user)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/market-space/profile/legal', $this->legalPayload())->assertOk();
        $this->post('/api/market-space/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/market-space/profile/legal/identity-document', ['document' => UploadedFile::fake()->create('cip.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/market-space/profile/submit')->assertOk();

        $this->assertSame(RegistrationStatus::Pending, $registration->fresh()->status);
    }

    // --- Verrous ----------------------------------------------------------

    public function test_business_routes_are_closed_until_the_dossier_is_approved(): void
    {
        foreach ([RegistrationStatus::ProfileIncomplete, RegistrationStatus::Pending, RegistrationStatus::Rejected] as $status) {
            $this->actingGaragiste($status);

            $this->getJson('/api/garage/products')
                ->assertForbidden()
                ->assertJsonPath('code', 'registration_not_approved')
                ->assertJsonPath('registration_status', $status->value);
            $this->getJson('/api/garage/appointments')->assertForbidden();
            $this->getJson('/api/garage/conversations')->assertForbidden();
            $this->getJson('/api/garage/profile')->assertOk();
        }

        $this->actingGaragiste(RegistrationStatus::Approved);
        $this->getJson('/api/garage/products')->assertOk();
    }

    public function test_business_routes_of_the_market_space_are_closed_too(): void
    {
        $user = User::factory()->marketSpace()->create();
        ProfessionalRegistration::factory()->for($user)->create();
        MarketSpaceAccount::factory()->complete()->for($user)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/market-space/products')->assertForbidden()->assertJsonPath('code', 'registration_not_approved');
    }

    public function test_profile_writes_are_locked_while_the_dossier_is_under_review(): void
    {
        $registration = $this->actingGaragiste(RegistrationStatus::Pending);
        $garage = $registration->user->garage;

        $this->putJson('/api/garage/profile', ['name' => 'Autre nom'])->assertStatus(409)->assertJsonPath('code', 'registration_under_review');
        $this->putJson('/api/garage/profile/opening-hours', ['opening_hours' => []])->assertStatus(409);
        $this->post('/api/garage/profile/images', ['images' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])->assertStatus(409);
        $this->deleteJson("/api/garage/profile/images/{$garage->images()->first()->id}")->assertStatus(409);
        $this->putJson('/api/garage/profile/legal', $this->legalPayload())->assertStatus(409)->assertJsonPath('code', 'registration_under_review');
        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(409);

        // Lecture et téléchargement restent possibles.
        $this->getJson('/api/garage/profile')->assertOk();
        Storage::disk('local')->put('registration-documents/rccm.pdf', 'pdf');
        $this->get('/api/garage/profile/legal/document')->assertOk();
    }

    public function test_legal_info_and_document_are_locked_once_approved(): void
    {
        $this->actingGaragiste(RegistrationStatus::Approved);

        $this->putJson('/api/garage/profile/legal', $this->legalPayload())->assertStatus(409)->assertJsonPath('code', 'legal_info_locked');
        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'legal_info_locked');
    }

    public function test_an_approved_account_can_still_edit_its_public_profile(): void
    {
        $this->actingGaragiste(RegistrationStatus::Approved);

        $this->putJson('/api/garage/profile/opening-hours', ['opening_hours' => []])->assertStatus(422);
    }

    // --- IFU / NPI ----------------------------------------------------------

    public function test_the_ifu_must_have_exactly_13_digits(): void
    {
        $this->actingGaragiste();

        foreach (['123456789012', '12345678901234', '12345678901AB'] as $invalid) {
            $this->putJson('/api/garage/profile/legal', $this->legalPayload(['ifu' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['ifu' => 'L\'IFU doit comporter exactement 13 chiffres.']);
        }

        $this->putJson('/api/garage/profile/legal', $this->legalPayload(['ifu' => '0123456789012']))->assertOk();
    }

    public function test_the_npi_must_have_exactly_10_digits(): void
    {
        $this->actingGaragiste();

        // 14 chiffres = numéro du certificat ANIP, pas le NPI.
        foreach (['123456789', '12345678901', '12345678901234', '12345678AB'] as $invalid) {
            $this->putJson('/api/garage/profile/legal', $this->legalPayload(['npi' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['npi' => 'Le NPI doit comporter exactement 10 chiffres.']);
        }

        $this->putJson('/api/garage/profile/legal', $this->legalPayload(['npi' => '0123456789']))->assertOk();
    }

    public function test_legal_info_is_required_and_ifu_npi_are_not_unique(): void
    {
        $this->actingGaragiste(withLegal: false);

        $this->putJson('/api/garage/profile/legal', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_registration_number', 'ifu', 'npi']);

        // Même IFU/NPI qu'un autre dossier (même structure, deux comptes — règle 2).
        ProfessionalRegistration::factory()->marketSpace()->create(['ifu' => '1234567890123', 'npi' => '1234567890']);
        $this->putJson('/api/garage/profile/legal', $this->legalPayload())->assertOk();
    }

    // --- Document -----------------------------------------------------------

    public function test_uploading_a_new_document_replaces_the_previous_one(): void
    {
        $registration = $this->actingGaragiste(withLegal: false);

        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm-1.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('meta.legal.has_business_registration_document', true);
        $first = $registration->businessRegistrationDocument()->first();
        Storage::disk('local')->assertExists($first->path);

        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->image('rccm-2.jpg')], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1, $registration->documents()->where('type', RegistrationDocumentType::BusinessRegistration)->count());
        Storage::disk('local')->assertMissing($first->path);
        Storage::disk('local')->assertExists($registration->businessRegistrationDocument()->first()->path);
    }

    public function test_the_document_must_be_a_pdf_or_an_image_of_at_most_10_mb(): void
    {
        $this->actingGaragiste(withLegal: false);

        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm.docx', 100)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm.pdf', 10241, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    public function test_a_professional_downloads_only_its_own_document(): void
    {
        $this->actingGaragiste(withLegal: false);
        $this->get('/api/garage/profile/legal/document', ['Accept' => 'application/json'])->assertNotFound();

        $this->post('/api/garage/profile/legal/document', ['document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $this->get('/api/garage/profile/legal/document')->assertOk()->assertDownload();
    }

    // --- Profil et confidentialité ------------------------------------------

    public function test_the_profile_meta_exposes_legal_status_registration_and_legal_info_to_its_owner(): void
    {
        $this->actingGaragiste(RegistrationStatus::Rejected);

        $this->getJson('/api/garage/profile')
            ->assertOk()
            ->assertJsonPath('meta.profile_status.is_complete', true)
            ->assertJsonPath('meta.legal_status', ['is_complete' => true, 'missing_fields' => []])
            ->assertJsonPath('meta.registration.status', RegistrationStatus::Rejected->value)
            ->assertJsonPath('meta.registration.status_label', 'Rejeté')
            ->assertJsonPath('meta.registration.rejection_reason', 'Photo floue.')
            ->assertJsonPath('meta.registration.submitted_at', null)
            ->assertJsonPath('meta.legal.ifu', '1234567890123')
            ->assertJsonPath('meta.legal.npi', '1234567890')
            ->assertJsonPath('meta.legal.business_registration_number', 'RB/COT/24 B 12345')
            ->assertJsonPath('meta.legal.has_business_registration_document', true)
            ->assertJsonPath('meta.legal.has_identity_certificate_document', true)
            ->assertJsonMissingPath('data.ifu')
            ->assertJsonMissingPath('data.npi');
    }

    public function test_the_session_exposes_the_profile_status_whatever_the_registration_status(): void
    {
        $this->actingGaragiste(RegistrationStatus::ProfileIncomplete, completeProfile: false);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.profile_status.is_complete', false)
            ->assertJsonMissingPath('data.professional_registration.ifu');
    }

    public function test_legal_info_never_appears_in_public_or_mobile_resources(): void
    {
        $user = User::factory()->garagiste()->create();
        ProfessionalRegistration::factory()->approved()->for($user)->create(['ifu' => '1234567890123', 'npi' => '1234567890', 'business_registration_number' => 'RB/COT/24 B 99999']);
        $garage = Garage::factory()->complete()->for($user)->create();

        foreach ([
            '/api/mobile/garages',
            "/api/mobile/garages/{$garage->id}",
            '/api/mobile/search/nearby?latitude=6.4&longitude=2.4',
        ] as $url) {
            $content = $this->getJson($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('1234567890123', $content, $url);
            $this->assertStringNotContainsString('1234567890', $content, $url);
            $this->assertStringNotContainsString('RB/COT/24 B 99999', $content, $url);
            $this->assertStringNotContainsString('"ifu"', $content, $url);
            $this->assertStringNotContainsString('"npi"', $content, $url);
        }
    }

    public function test_a_complete_but_pending_profile_appears_in_no_mobile_route(): void
    {
        $user = User::factory()->garagiste()->create();
        ProfessionalRegistration::factory()->for($user)->create();
        $garage = Garage::factory()->complete()->for($user)->create(['name' => 'Garage En Attente']);
        $marketUser = User::factory()->marketSpace()->create();
        ProfessionalRegistration::factory()->for($marketUser)->create();
        $account = MarketSpaceAccount::factory()->complete()->for($marketUser)->create(['name' => 'Boutique En Attente']);

        $this->getJson('/api/mobile/garages')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/mobile/garages/{$garage->id}")->assertNotFound();
        $this->getJson("/api/mobile/garages/{$garage->id}/reviews")->assertNotFound();
        $this->getJson('/api/mobile/market-space-accounts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/mobile/market-space-accounts/{$account->id}")->assertNotFound();
        $this->getJson("/api/mobile/market-space-accounts/{$account->id}/reviews")->assertNotFound();
        $this->getJson('/api/mobile/search/nearby?name=attente')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/mobile/search/nearby?latitude=6.4&longitude=2.4&radius_km=100')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/mobile/conversations', ['garage_id' => $garage->id])->assertNotFound();
        $this->postJson('/api/mobile/conversations', ['market_space_account_id' => $account->id])->assertNotFound();
        $this->postJson('/api/mobile/appointments', ['garage_id' => $garage->id, 'requested_at' => now()->addDay()->toIso8601String(), 'description' => 'Panne'])->assertNotFound();
    }
}
