<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Vérifie un ID token Google (JWT) envoyé par l'app Flutter après un Google
 * Sign-In natif — pas de flux Socialite redirect()/callback() puisque le
 * backend ne sert aucune vue (CLAUDE.md §5, ajout v0.5).
 */
class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @return array{sub: string, email: string, name: string}
     */
    public function verify(string $idToken): array
    {
        try {
            $payload = (array) JWT::decode($idToken, JWK::parseKeySet($this->certificates()));
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'id_token' => ['Jeton Google invalide ou expiré.'],
            ]);
        }

        if (! in_array($payload['iss'] ?? null, self::ISSUERS, true)) {
            throw ValidationException::withMessages([
                'id_token' => ['Émetteur du jeton Google invalide.'],
            ]);
        }

        if (($payload['aud'] ?? null) !== config('services.google.client_id')) {
            throw ValidationException::withMessages([
                'id_token' => ['Ce jeton Google est destiné à une autre application.'],
            ]);
        }

        if (empty($payload['email_verified'])) {
            throw ValidationException::withMessages([
                'id_token' => ['Cet email Google n\'est pas vérifié.'],
            ]);
        }

        return [
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'name' => $payload['name'] ?? $payload['email'],
        ];
    }

    /**
     * JWKS Google mis en cache : la vérification de signature reste locale
     * (pas d'appel réseau à chaque connexion), les clés tournent rarement.
     *
     * @return array<string, mixed>
     */
    private function certificates(): array
    {
        return Cache::remember('google.oauth.certs', now()->addHours(6), function () {
            return Http::get(self::CERTS_URL)->throw()->json();
        });
    }
}
