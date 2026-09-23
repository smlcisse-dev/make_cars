<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\ReactivationRequest;
use App\Models\ReactivationRequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pièces jointes facultatives d'une demande de réactivation (CLAUDE.md §5,
 * ajout v0.28) : 0 à 5 photos ou PDF de 5 Mo au plus, sur le disque privé
 * des médias, téléchargeables par le propriétaire et l'admin seulement.
 */
class ReactivationRequestAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/garage/profile/reactivation-request';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['filesystems.private_media_disk' => 'private_media_test']);
        Storage::fake('private_media_test');
    }

    private function suspendedGaragiste(): ProfessionalRegistration
    {
        $registration = ProfessionalRegistration::factory()->approved()->suspended()->create();
        Garage::factory()->complete()->for($registration->user)->create();

        return $registration;
    }

    /**
     * Demande avec pièces jointes, envoyée par le garagiste propriétaire.
     */
    private function requestWithAttachments(ProfessionalRegistration $registration, array $files): ReactivationRequest
    {
        Sanctum::actingAs($registration->user);
        $this->post(self::URL, ['message' => 'Remboursement effectué.', 'attachments' => $files], ['Accept' => 'application/json'])
            ->assertCreated();

        return $registration->reactivationRequests()->firstOrFail();
    }

    // --- Envoi ------------------------------------------------------------------

    public function test_a_request_without_attachment_stays_valid(): void
    {
        Sanctum::actingAs($this->suspendedGaragiste()->user);

        $this->postJson(self::URL, ['message' => 'Corrigé.'])
            ->assertCreated()
            ->assertJsonPath('data.attachments', []);
    }

    public function test_a_request_with_one_attachment(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->create('remboursement.pdf', 200, 'application/pdf')]);

        $attachment = $request->attachments()->sole();
        $this->assertSame('remboursement.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame('private_media_test', $attachment->disk);
        Storage::disk('private_media_test')->assertExists($attachment->path);
    }

    public function test_a_request_with_five_attachments(): void
    {
        $registration = $this->suspendedGaragiste();
        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpeg'),
            UploadedFile::fake()->image('c.png'),
            UploadedFile::fake()->create('d.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('e.pdf', 100, 'application/pdf'),
        ];

        $request = $this->requestWithAttachments($registration, $files);

        $this->assertSame(5, $request->attachments()->count());
    }

    public function test_six_attachments_are_refused(): void
    {
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);
        $files = array_map(fn (int $i) => UploadedFile::fake()->image("photo{$i}.jpg"), range(1, 6));

        $this->post(self::URL, ['message' => 'Corrigé.', 'attachments' => $files], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments');

        $this->assertDatabaseCount('reactivation_requests', 0);
    }

    public function test_a_wrong_format_is_refused(): void
    {
        Sanctum::actingAs($this->suspendedGaragiste()->user);

        $this->post(self::URL, [
            'message' => 'Corrigé.',
            'attachments' => [UploadedFile::fake()->create('preuve.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_a_file_over_five_megabytes_is_refused(): void
    {
        Sanctum::actingAs($this->suspendedGaragiste()->user);

        $this->post(self::URL, [
            'message' => 'Corrigé.',
            'attachments' => [UploadedFile::fake()->create('preuve.pdf', 5121, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_no_file_remains_when_an_attachment_fails(): void
    {
        // Échec simulé à l'enregistrement de la deuxième pièce jointe, après
        // l'écriture de son fichier : tout est annulé, disque compris.
        $calls = 0;
        ReactivationRequestAttachment::creating(function () use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('Échec simulé.');
            }
        });
        $registration = $this->suspendedGaragiste();
        Sanctum::actingAs($registration->user);

        $this->post(self::URL, [
            'message' => 'Corrigé.',
            'attachments' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ], ['Accept' => 'application/json'])->assertServerError();

        $this->assertSame([], Storage::disk('private_media_test')->allFiles());
        $this->assertDatabaseCount('reactivation_requests', 0);
        $this->assertDatabaseCount('reactivation_request_attachments', 0);
    }

    // --- Ressources ---------------------------------------------------------------

    public function test_attachments_are_exposed_in_the_session_and_the_admin_record(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->create('preuve.pdf', 100, 'application/pdf')]);
        $attachment = $request->attachments()->sole();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.professional_registration.latest_reactivation_request.attachments.0.original_name', 'preuve.pdf')
            ->assertJsonPath('data.professional_registration.latest_reactivation_request.attachments.0.download_url', url("/api/garage/profile/reactivation-requests/{$request->id}/attachments/{$attachment->id}"));

        Sanctum::actingAs(User::factory()->admin()->create());
        $adminUrl = url("/api/admin/registrations/{$registration->id}/reactivation-requests/{$request->id}/attachments/{$attachment->id}");

        $this->getJson("/api/admin/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_reactivation_request.attachments.0.download_url', $adminUrl)
            ->assertJsonPath('data.reactivation_requests.0.attachments.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.reactivation_requests.0.attachments.0.size', $attachment->size);
    }

    // --- Téléchargement ---------------------------------------------------------

    public function test_the_owner_downloads_an_attachment(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->create('preuve.pdf', 100, 'application/pdf')]);
        $attachment = $request->attachments()->sole();

        $this->get("/api/garage/profile/reactivation-requests/{$request->id}/attachments/{$attachment->id}")
            ->assertOk()
            ->assertDownload('preuve.pdf');
    }

    public function test_the_admin_downloads_an_attachment(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->image('recu.jpg')]);
        $attachment = $request->attachments()->sole();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->get("/api/admin/registrations/{$registration->id}/reactivation-requests/{$request->id}/attachments/{$attachment->id}")
            ->assertOk()
            ->assertDownload('recu.jpg');
    }

    public function test_another_professional_gets_a_404(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->image('recu.jpg')]);
        $attachment = $request->attachments()->sole();

        Sanctum::actingAs($this->suspendedGaragiste()->user);

        $this->getJson("/api/garage/profile/reactivation-requests/{$request->id}/attachments/{$attachment->id}")
            ->assertNotFound();
    }

    public function test_an_attachment_of_another_request_gets_a_404(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->image('recu.jpg')]);
        $foreign = ReactivationRequestAttachment::factory()->create();

        $this->getJson("/api/garage/profile/reactivation-requests/{$request->id}/attachments/{$foreign->id}")
            ->assertNotFound();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/admin/registrations/{$registration->id}/reactivation-requests/{$request->id}/attachments/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_a_request_of_another_registration_gets_a_404_for_the_admin(): void
    {
        $registration = $this->suspendedGaragiste();
        $request = $this->requestWithAttachments($registration, [UploadedFile::fake()->image('recu.jpg')]);
        $attachment = $request->attachments()->sole();
        $otherRegistration = ProfessionalRegistration::factory()->approved()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/admin/registrations/{$otherRegistration->id}/reactivation-requests/{$request->id}/attachments/{$attachment->id}")
            ->assertNotFound();
    }
}
