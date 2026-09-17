<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\RequestExpressClaimRequest;
use App\Services\ExpressClientClaimService;
use Illuminate\Http\JsonResponse;

/**
 * Réclamation d'un compte "express" par son propriétaire réel (CLAUDE.md §5,
 * ajout v0.17).
 */
class ExpressClaimController extends Controller
{
    public function __construct(private readonly ExpressClientClaimService $claimService) {}

    public function request(RequestExpressClaimRequest $request): JsonResponse
    {
        $this->claimService->requestClaim($request->string('email')->toString());

        return $this->success(message: 'Un email vous a été envoyé pour définir votre mot de passe.');
    }
}
