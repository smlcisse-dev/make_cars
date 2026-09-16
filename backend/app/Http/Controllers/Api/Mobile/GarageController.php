<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\GarageResource;
use App\Models\Garage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GarageController extends Controller
{
    /**
     * Liste des garages visibles publiquement (compte validé — CLAUDE.md §5
     * règle 4). La recherche géographique par proximité est un module à part
     * (carte, calcul de distance — cf. CLAUDE.md §7 points ouverts).
     */
    public function index(): AnonymousResourceCollection
    {
        $garages = Garage::query()
            ->whereHas('user.professionalRegistration', fn ($query) => $query->where('status', RegistrationStatus::Approved))
            ->with(['openingHours', 'images'])
            ->paginate();

        return GarageResource::collection($garages);
    }

    public function show(Garage $garage): GarageResource
    {
        abort_unless($garage->isPubliclyVisible(), 404);

        return new GarageResource($garage->load(['openingHours', 'images']));
    }
}
