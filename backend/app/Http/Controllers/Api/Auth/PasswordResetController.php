<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

/**
 * Mot de passe oublié par code email (CLAUDE.md §5, ajout v0.29). Aucun
 * token n'est renvoyé : la personne se reconnecte avec son nouveau mot de
 * passe.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->requestCode($request->string('email')->toString());

        return $this->success(message: 'Si un compte existe avec cette adresse, un code vient d\'être envoyé.');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        return $this->success(message: 'Votre mot de passe a été modifié. Connectez-vous avec votre nouveau mot de passe.');
    }
}
