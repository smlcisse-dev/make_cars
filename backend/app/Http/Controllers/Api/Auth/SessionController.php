<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly GoogleIdTokenVerifier $googleIdTokenVerifier,
    ) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->string('email')->toString(), $request->string('password')->toString());
        $token = $this->authService->issueToken($user);

        return $this->success([
            'user' => new UserResource($user->load('professionalRegistration')),
            'token' => $token->plainTextToken,
        ], 'Connexion réussie.');
    }

    /**
     * Connexion Google — réservée aux automobilistes (CLAUDE.md §5, ajout v0.5).
     */
    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $googleUser = $this->googleIdTokenVerifier->verify($request->string('id_token')->toString());
        $user = $this->authService->loginWithGoogle($googleUser);
        $token = $this->authService->issueToken($user);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ], 'Connexion réussie.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load('professionalRegistration')));
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Déconnexion réussie.');
    }
}
