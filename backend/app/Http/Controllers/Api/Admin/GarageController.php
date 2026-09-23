<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\GarageResource;
use App\Models\Garage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GarageController extends Controller
{
    /**
     * Supervision admin en lecture (CLAUDE.md §5, règle 8) : garages au
     * dossier approuvé, suspendus compris. Les dossiers en cours (profil créé
     * dès la vérification de l'email) se consultent via /admin/registrations
     * (CLAUDE.md §5, ajout v0.26).
     */
    public function index(): AnonymousResourceCollection
    {
        $garages = Garage::query()->withApprovedRegistration()->with(['user', 'openingHours', 'images'])->paginate();

        return GarageResource::collection($garages);
    }

    public function show(Garage $garage): GarageResource
    {
        abort_unless($garage->hasApprovedRegistration(), 404, 'Structure introuvable : son dossier n\'est pas approuvé (voir /admin/registrations).');

        return new GarageResource($garage->load(['openingHours', 'images', 'department', 'commune', 'arrondissement']));
    }
}
