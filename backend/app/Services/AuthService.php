<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    /**
     * @param  array{name: string, email: string, phone: ?string, password: string}  $data
     */
    public function registerAutomobiliste(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => AccountType::Automobiliste,
        ]);
    }

    /**
     * @throws ValidationException si les identifiants sont invalides.
     */
    public function login(string $email, string $password): User
    {
        if (! Auth::once(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['Ces identifiants ne correspondent à aucun compte.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function issueToken(User $user): NewAccessToken
    {
        return $user->createToken($user->role->value);
    }

    /**
     * Connexion (ou création à la première connexion) via Google — réservée
     * aux automobilistes ; les deux méthodes de connexion coexistent pour ce
     * type de compte (CLAUDE.md §5, ajout v0.5).
     *
     * @param  array{sub: string, email: string, name: string}  $googleUser
     */
    public function loginWithGoogle(array $googleUser): User
    {
        $user = User::where('google_id', $googleUser['sub'])->first()
            ?? User::where('email', $googleUser['email'])->first();

        if ($user && $user->role !== AccountType::Automobiliste) {
            throw ValidationException::withMessages([
                'id_token' => ['Cet email est associé à un compte professionnel ; la connexion Google est réservée aux automobilistes.'],
            ]);
        }

        if (! $user) {
            return User::create([
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'google_id' => $googleUser['sub'],
                'role' => AccountType::Automobiliste,
            ]);
        }

        if ($user->google_id !== $googleUser['sub']) {
            $user->update(['google_id' => $googleUser['sub']]);
        }

        return $user;
    }
}
