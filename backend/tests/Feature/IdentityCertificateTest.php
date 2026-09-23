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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Certificat d'Identification Personnelle (CIP) du dossier d'inscription
 * (CLAUDE.md §5, ajout v0.27) : sert uniquement à vérifier le NPI, jamais
 * public.
 */
class IdentityCertificateTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/garage/profile/legal/identity-document';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    /**
     * Garagiste connecté, profil complet, informations légales et registre
     * de commerce fournis ; CIP fourni seulement si demandé.
     */
    private function actingGaragiste(RegistrationStatus $status = RegistrationStatus::ProfileIncomplete, bool $withCertificate = false): ProfessionalRegistration
    {
        $user = User::factory()->garagiste()->create();
        $registration = ProfessionalRegistration::factory()->for($user)->withBusinessRegistrationDocument()->create([
            'status' => $status,
            'business_registration_number' => 'RB/COT/24 B 12345',
            'ifu' => '1234567890123',
            'npi' => '1234567890',
            'submitted_at' => $status === RegistrationStatus::Pending ? now() : null,
            'reviewed_at' => $status === RegistrationStatus::Approved ? now() : null,
        ]);

        if ($withCertificate) {
            $registration->documents()->create(['type' => RegistrationDocumentType::IdentityCertificate, 'disk' => 'local', 'path' => 'registration-documents/cip-existant.pdf']);
        }

        Garage::factory()->complete()->for($user)->create();
        Sanctum::actingAs($user);

        return $registration;
    }

    private function upload(UploadedFile $file, string $url = self::URL): TestResponse
    {
        return $this->post($url, ['document' => $file], ['Accept' => 'application/json']);
    }

    // --- Envoi ----------------------------------------------------------------

    public function test_a_professional_uploads_its_identity_certificate(): void
    {
        $registration = $this->actingGaragiste();

        $this->upload(UploadedFile::fake()->create('cip.pdf', 100, 'application/pdf'))
            ->assertCreated()
            ->assertJsonPath('meta.legal.has_identity_certificate_document', true)
            ->assertJsonPath('meta.legal_status.is_complete', true);

        $document = $registration->identityCertificateDocument()->first();
        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->path);
        // Le registre de commerce n'est pas touché.
        $this->assertNotNull($registration->businessRegistrationDocument()->first());
    }

    public function test_the_market_space_has_the_same_endpoint(): void
    {
        $user = User::factory()->marketSpace()->create();
        $registration = ProfessionalRegistration::factory()->for($user)->profileIncomplete()->create();
        MarketSpaceAccount::factory()->complete()->for($user)->create();
        Sanctum::actingAs($user);

        $this->upload(UploadedFile::fake()->image('cip.png'), '/api/market-space/profile/legal/identity-document')->assertCreated();
        $this->get('/api/market-space/profile/legal/identity-document')->assertOk()->assertDownload();
        $this->assertNotNull($registration->identityCertificateDocument()->first());
    }

    public function test_the_certificate_must_be_a_pdf_or_an_image_of_at_most_10_mb(): void
    {
        $this->actingGaragiste();

        $this->upload(UploadedFile::fake()->create('cip.docx', 100))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->upload(UploadedFile::fake()->create('cip.pdf', 10241, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->post(self::URL, [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    public function test_a_new_certificate_replaces_the_previous_one_and_deletes_its_file(): void
    {
        $registration = $this->actingGaragiste();

        $this->upload(UploadedFile::fake()->create('cip-1.pdf', 100, 'application/pdf'))->assertCreated();
        $first = $registration->identityCertificateDocument()->first();
        Storage::disk('local')->assertExists($first->path);

        $this->upload(UploadedFile::fake()->image('cip-2.jpg'))->assertCreated();

        $this->assertSame(1, $registration->documents()->where('type', RegistrationDocumentType::IdentityCertificate)->count());
        $this->assertModelMissing($first);
        Storage::disk('local')->assertMissing($first->path);
        Storage::disk('local')->assertExists($registration->identityCertificateDocument()->first()->path);
        // Le registre de commerce n'est pas remplacé par un envoi de CIP.
        $this->assertSame(1, $registration->documents()->where('type', RegistrationDocumentType::BusinessRegistration)->count());
    }

    // --- Téléchargement ---------------------------------------------------------

    public function test_a_professional_downloads_only_its_own_certificate(): void
    {
        $other = $this->actingGaragiste();
        $this->upload(UploadedFile::fake()->createWithContent('cip-autre.pdf', 'CIP-AUTRE'))->assertCreated();

        $this->actingGaragiste();
        $this->get(self::URL, ['Accept' => 'application/json'])->assertNotFound();

        $this->upload(UploadedFile::fake()->createWithContent('cip.pdf', 'CIP-MOI'))->assertCreated();
        $response = $this->get(self::URL)->assertOk()->assertDownload();
        $this->assertSame('CIP-MOI', $response->streamedContent());
        $this->assertNotNull($other->identityCertificateDocument()->first());
    }

    public function test_the_certificate_download_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::URL)->assertUnauthorized();
    }

    // --- Verrous ------------------------------------------------------------------

    public function test_the_certificate_cannot_be_replaced_while_under_review(): void
    {
        $this->actingGaragiste(RegistrationStatus::Pending, withCertificate: true);

        $this->upload(UploadedFile::fake()->create('cip.pdf', 100, 'application/pdf'))
            ->assertStatus(409)
            ->assertJsonPath('code', 'registration_under_review');

        Storage::disk('local')->put('registration-documents/cip-existant.pdf', 'pdf');
        $this->get(self::URL)->assertOk();
    }

    public function test_the_certificate_cannot_be_replaced_once_approved(): void
    {
        $this->actingGaragiste(RegistrationStatus::Approved, withCertificate: true);

        $this->upload(UploadedFile::fake()->create('cip.pdf', 100, 'application/pdf'))
            ->assertStatus(409)
            ->assertJsonPath('code', 'legal_info_locked');
    }

    // --- Soumission -----------------------------------------------------------------

    public function test_submission_is_refused_without_the_certificate(): void
    {
        $this->actingGaragiste();

        $this->getJson('/api/garage/profile')
            ->assertJsonPath('meta.legal.has_identity_certificate_document', false)
            ->assertJsonPath('meta.legal_status.missing_fields', ['identity_certificate_document']);

        $this->postJson('/api/garage/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'registration_incomplete')
            ->assertJsonPath('missing_fields', [])
            ->assertJsonPath('missing_legal_fields', ['identity_certificate_document']);

        $this->upload(UploadedFile::fake()->create('cip.pdf', 100, 'application/pdf'))->assertCreated();
        $this->postJson('/api/garage/profile/submit')->assertOk();
    }

    public function test_a_rejected_dossier_needs_the_certificate_to_be_submitted_again(): void
    {
        $this->actingGaragiste(RegistrationStatus::Rejected);

        $this->postJson('/api/garage/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('missing_legal_fields', ['identity_certificate_document']);
    }

    // --- Existant, sans blocage rétroactif ------------------------------------------------

    public function test_an_approved_account_without_certificate_keeps_its_access(): void
    {
        $this->actingGaragiste(RegistrationStatus::Approved);

        $this->getJson('/api/garage/products')->assertOk();
        $this->getJson('/api/garage/profile')->assertOk();
    }

    public function test_a_pending_dossier_without_certificate_can_still_be_decided(): void
    {
        $registration = ProfessionalRegistration::factory()->withProfile()->withBusinessRegistrationDocument()->create();
        $rejected = ProfessionalRegistration::factory()->withProfile()->withBusinessRegistrationDocument()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();
        $this->postJson("/api/admin/registrations/{$rejected->id}/reject", ['reason' => 'CIP manquante.'])->assertOk();
    }

    // --- Confidentialité ---------------------------------------------------------------

    public function test_the_admin_sees_the_certificate_in_the_dossier_and_downloads_it(): void
    {
        $registration = ProfessionalRegistration::factory()->withProfile()->withIdentityCertificateDocument()->create();
        $document = $registration->identityCertificateDocument()->first();
        Storage::disk('local')->put($document->path, 'CIP');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/admin/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('data.documents.0.type', RegistrationDocumentType::IdentityCertificate->value)
            ->assertJsonPath('data.documents.0.type_label', "Certificat d'Identification Personnelle (CIP)");

        $this->get("/api/admin/registrations/{$registration->id}/documents/{$document->id}")->assertOk()->assertDownload();
    }

    public function test_the_certificate_never_appears_in_public_mobile_or_session_resources(): void
    {
        $user = User::factory()->garagiste()->create();
        $registration = ProfessionalRegistration::factory()->approved()->for($user)->withIdentityCertificateDocument()->create();
        $garage = Garage::factory()->complete()->for($user)->create();
        $path = $registration->identityCertificateDocument()->first()->path;

        $urls = [
            '/api/mobile/garages',
            "/api/mobile/garages/{$garage->id}",
            '/api/mobile/search/nearby?latitude=6.4&longitude=2.4',
        ];

        foreach ($urls as $url) {
            $this->assertCertificateAbsent($this->getJson($url)->assertOk()->getContent(), $path, $url);
        }

        Sanctum::actingAs(User::factory()->create());
        $this->assertCertificateAbsent($this->getJson("/api/mobile/garages/{$garage->id}")->assertOk()->getContent(), $path, 'mobile connecté');

        Sanctum::actingAs($user);
        $this->assertCertificateAbsent($this->getJson('/api/auth/me')->assertOk()->getContent(), $path, '/auth/me');
    }

    private function assertCertificateAbsent(string $content, string $path, string $context): void
    {
        $this->assertStringNotContainsString('identity_certificate', $content, $context);
        $this->assertStringNotContainsString(basename($path), $content, $context);
        $this->assertStringNotContainsString('"documents"', $content, $context);
    }
}
