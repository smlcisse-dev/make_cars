<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\Controller;
use App\Models\Commune;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

/**
 * Données de référence du découpage administratif pour les selects en
 * cascade (Département → Commune → Arrondissement), publiques et en lecture
 * seule : mêmes routes pour le web et Flutter (CLAUDE.md §5, ajout v0.19).
 */
class LocationController extends Controller
{
    public function departments(): JsonResponse
    {
        return $this->cacheable(Department::query()->orderBy('name')->get(['id', 'name']));
    }

    public function communes(Department $department): JsonResponse
    {
        return $this->cacheable($department->communes()->orderBy('name')->get(['id', 'name']));
    }

    public function arrondissements(Commune $commune): JsonResponse
    {
        return $this->cacheable($commune->arrondissements()->orderBy('name')->get(['id', 'name']));
    }

    /**
     * Données de référence quasi immuables : le navigateur peut les garder un
     * jour, ce qui évite un aller-retour vers la base pour chaque sélection.
     */
    private function cacheable(mixed $data): JsonResponse
    {
        return $this->success($data)->header('Cache-Control', 'public, max-age=86400');
    }
}
