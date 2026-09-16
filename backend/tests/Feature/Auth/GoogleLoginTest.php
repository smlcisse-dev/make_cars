<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    private array $jwk;

    private const KID = 'test-kid';

    protected function setUp(): void
    {
        parent::setUp();

        // Chaque test génère sa propre paire de clés : on force le
        // rafraîchissement du JWKS mis en cache par GoogleIdTokenVerifier
        // pour éviter qu'un test hérite des clés d'un test précédent.
        Cache::forget('google.oauth.certs');

        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKey = $privateKeyPem;
        $details = openssl_pkey_get_details($keyPair)['rsa'];

        $this->jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::KID,
            'n' => JWT::urlsafeB64Encode($details['n']),
            'e' => JWT::urlsafeB64Encode($details['e']),
        ];

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$this->jwk]]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function googleIdToken(array $overrides = []): string
    {
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => config('services.google.client_id'),
            'sub' => '1234567890',
            'email' => 'automobiliste@example.com',
            'email_verified' => true,
            'name' => 'Jean Dupont',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($payload, $this->privateKey, 'RS256', self::KID);
    }

    public function test_a_new_automobiliste_account_is_created_on_first_google_login(): void
    {
        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => $this->googleIdToken(),
        ]);

        $response->assertOk()->assertJsonPath('data.user.email', 'automobiliste@example.com');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'email' => 'automobiliste@example.com',
            'google_id' => '1234567890',
            'role' => AccountType::Automobiliste->value,
        ]);
    }

    public function test_an_existing_automobiliste_is_matched_by_google_id_on_a_later_login(): void
    {
        $user = User::factory()->create([
            'email' => 'automobiliste@example.com',
            'google_id' => '1234567890',
        ]);

        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => $this->googleIdToken(),
        ]);

        $response->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_existing_automobiliste_created_by_email_password_is_linked_by_email(): void
    {
        $user = User::factory()->create(['email' => 'automobiliste@example.com', 'google_id' => null]);

        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => $this->googleIdToken(),
        ]);

        $response->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'google_id' => '1234567890']);
    }

    public function test_google_login_is_refused_for_an_email_already_used_by_a_professional_account(): void
    {
        User::factory()->garagiste()->create(['email' => 'automobiliste@example.com']);

        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => $this->googleIdToken(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('id_token');
    }

    public function test_google_login_is_refused_when_the_email_is_not_verified(): void
    {
        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => $this->googleIdToken(['email_verified' => false]),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('id_token');
    }

    public function test_google_login_is_refused_when_the_token_audience_does_not_match(): void
    {
        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => $this->googleIdToken(['aud' => 'another-app.apps.googleusercontent.com']),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('id_token');
    }

    public function test_google_login_is_refused_with_a_malformed_token(): void
    {
        $response = $this->postJson('/api/auth/login/google', [
            'id_token' => 'not-a-valid-jwt',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('id_token');
    }
}
