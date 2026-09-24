<?php

namespace App\Http\Controllers\Api\Professional;

use App\Http\Controllers\Api\Controller;
use App\Services\ProfessionalDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord du professionnel connecté, commun aux espaces Garagiste et
 * Market Space (ajout 2026-09-24) — même principe que
 * RegistrationDossierController : un seul contrôleur plutôt qu'une copie par
 * espace. Le rôle (garde `role:` de chaque groupe de routes) détermine le
 * profil lu.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ProfessionalDashboardService $dashboardService) {}

    public function show(Request $request): JsonResponse
    {
        $seller = $request->user()->professionalProfile();

        abort_if($seller === null, 404, 'Profil introuvable pour ce compte. Contactez le support.');

        return $this->success($this->dashboardService->build($seller));
    }
}
