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
}
