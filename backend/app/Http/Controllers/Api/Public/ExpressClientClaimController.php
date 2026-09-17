<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\ConfirmExpressClaimRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ExpressClientClaimService;
use Illuminate\Http\JsonResponse;

/**
 * Réclamation d'un compte "express" via le lien signé reçu par email
 * (CLAUDE.md §5, ajout v0.17). Routes protégées par le middleware `signed`
 * (pas de Sanctum). GET et POST partagent la même URL signée (voir
 * ExpressClientClaimService) : GET confirme la validité du lien (réponse
 * JSON brute — API REST pure, CLAUDE.md §4 ; page de confirmation laissée au
 * futur frontend Vue, même point ouvert que la décision de devis par email,
 * ajout v0.9), POST définit effectivement le mot de passe.
 */
class ExpressClientClaimController extends Controller
{
    public function __construct(private readonly ExpressClientClaimService $claimService) {}

    public function show(User $user): JsonResponse
    {
        return $this->success($this->claimService->show($user));
    }

    public function confirm(ConfirmExpressClaimRequest $request, User $user): JsonResponse
    {
        $user = $this->claimService->confirm($user, $request->string('password')->toString());

        return $this->success(new UserResource($user), 'Mot de passe défini. Vous pouvez désormais vous connecter normalement.');
    }
}
