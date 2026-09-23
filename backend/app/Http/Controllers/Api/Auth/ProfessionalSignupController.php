<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\RegisterProfessionalRequest;
use App\Http\Requests\Auth\VerifyProfessionalRegistrationRequest;
use App\Http\Resources\PendingProfessionalRegistrationResource;
use App\Services\ProfessionalSignupService;
use Illuminate\Http\JsonResponse;

/**
 * Inscription professionnelle courte avec vérification de l'email par code
 * (CLAUDE.md §5, ajout v0.26). Aucun token n'est jamais renvoyé : le
 * professionnel se connecte ensuite via /auth/login.
 */
class ProfessionalSignupController extends Controller
{
    public function __construct(private readonly ProfessionalSignupService $signupService) {}

    public function store(RegisterProfessionalRequest $request): JsonResponse
    {
        $pending = $this->signupService->start($request->validated());

        return $this->success(
            new PendingProfessionalRegistrationResource($pending),
            'Un code de vérification a été envoyé à votre adresse email.',
            201,
        );
    }

    public function verify(VerifyProfessionalRegistrationRequest $request, string $uuid): JsonResponse
    {
        $user = $this->signupService->verify($uuid, $request->string('code')->toString());

        return $this->success(
            ['email' => $user->email, 'account_type' => $user->role->value],
            'Votre compte a été créé. Connectez-vous pour compléter votre profil.',
            201,
        );
    }

    public function resend(string $uuid): JsonResponse
    {
        $pending = $this->signupService->resend($uuid);

        return $this->success(
            new PendingProfessionalRegistrationResource($pending),
            'Un nouveau code vous a été envoyé.',
        );
    }
}
