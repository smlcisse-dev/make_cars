<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * Disques Supabase Storage (API compatible S3) utilisés en production, où le
 * disque du serveur est effacé à chaque redémarrage (DEPLOIEMENT.md) : un
 * bucket privé pour les justificatifs et médias privés, un bucket public
 * pour les photos. Aucun appel réseau : le client S3 répond depuis un bucket
 * en mémoire, ce qui vérifie le vrai chemin (pilote S3, envoi, lecture,
 * téléchargement authentifié, URL publique).
 */
class SupabaseStorageTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<string, string>> bucket => (clé => contenu) */
    private array $buckets = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $disk = [
            'key' => 'test-key',
            'secret' => 'test-secret',
            'endpoint' => 'https://projet.storage.supabase.co/storage/v1/s3',
            'handler' => $this->fakeS3(...),
        ];

        config([
            'filesystems.disks.supabase' => [...config('filesystems.disks.supabase'), ...$disk, 'bucket' => 'prive'],
            'filesystems.disks.supabase_public' => [...config('filesystems.disks.supabase_public'), ...$disk,
                'bucket' => 'public-media',
                'url' => 'https://projet.supabase.co/storage/v1/object/public/public-media',
            ],
            'filesystems.kyc_documents_disk' => 'supabase',
            'filesystems.private_media_disk' => 'supabase',
            'filesystems.public_media_disk' => 'supabase_public',
        ]);
    }

    /**
     * Bucket S3 en mémoire : juste ce dont Flysystem a besoin pour écrire,
     * lire, tester l'existence et supprimer un fichier.
     */
    private function fakeS3(CommandInterface $command, RequestInterface $request)
    {
        $bucket = $command['Bucket'];
        $key = $command['Key'] ?? null;

        $result = match ($command->getName()) {
            'PutObject' => (function () use ($bucket, $key, $command) {
                $this->buckets[$bucket][$key] = (string) $command['Body'];

                return new Result([]);
            })(),
            'HeadObject' => isset($this->buckets[$bucket][$key])
                ? new Result(['ContentLength' => strlen($this->buckets[$bucket][$key]), 'ContentType' => 'application/pdf'])
                : null,
            'GetObject' => isset($this->buckets[$bucket][$key])
                ? new Result(['Body' => Utils::streamFor($this->buckets[$bucket][$key]), 'ContentType' => 'application/pdf'])
                : null,
            'DeleteObject' => (function () use ($bucket, $key) {
                unset($this->buckets[$bucket][$key]);

                return new Result([]);
            })(),
            default => new Result([]),
        };

        if ($result === null) {
            return Create::rejectionFor(new S3Exception('Not found', $command, ['code' => 'NotFound', 'response' => new Response(404)]));
        }

        return Create::promiseFor($result);
    }

    private function actingGaragiste(): User
    {
        $user = User::factory()->garagiste()->create();
        ProfessionalRegistration::factory()->for($user)->create(['status' => RegistrationStatus::ProfileIncomplete]);
        Garage::factory()->complete()->for($user)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_a_legal_document_goes_to_the_private_bucket_and_is_downloaded_through_the_api(): void
    {
        $this->actingGaragiste();

        $this->post('/api/garage/profile/legal/document', [
            'document' => UploadedFile::fake()->createWithContent('rccm.pdf', '%PDF-1.4 registre'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertCount(1, $this->buckets['prive']);
        $this->assertStringStartsWith('registration-documents/', array_key_first($this->buckets['prive']));

        $response = $this->get('/api/garage/profile/legal/document')->assertOk()->assertDownload();
        $this->assertSame('%PDF-1.4 registre', $response->streamedContent());
    }

    public function test_a_private_document_is_never_downloadable_by_another_professional(): void
    {
        $this->actingGaragiste();
        $this->post('/api/garage/profile/legal/document', [
            'document' => UploadedFile::fake()->createWithContent('rccm.pdf', '%PDF-1.4 registre'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingGaragiste();

        $this->getJson('/api/garage/profile/legal/document')->assertNotFound();
    }

    public function test_a_profile_photo_goes_to_the_public_bucket_with_its_public_url(): void
    {
        $user = $this->actingGaragiste();

        $this->post('/api/garage/profile/images', ['images' => [UploadedFile::fake()->image('local.jpg')]], ['Accept' => 'application/json'])
            ->assertSuccessful();

        $image = $user->garage->images()->reorder()->latest('id')->first();

        $this->assertSame('supabase_public', $image->disk);
        $this->assertArrayHasKey($image->path, $this->buckets['public-media']);
        $this->assertSame(
            "https://projet.supabase.co/storage/v1/object/public/public-media/{$image->path}",
            Storage::disk('supabase_public')->url($image->path),
        );
        $this->assertSame(Storage::disk('supabase_public')->url($image->path), $image->url());
    }
}
