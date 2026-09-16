<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\ProductStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RepairServiceStatus;
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
            ->whereHas('user.professionalRegistration', fn ($query) => $query->where('status', RegistrationStatus::Approved)->whereNull('suspended_at'))
            ->with(['openingHours', 'images'])
            ->paginate();

        return GarageResource::collection($garages);
    }

    /**
     * Les produits de la mini-boutique sont affichés sur le profil du garage,
     * pas dans une liste Market Space distincte (CLAUDE.md §5, ajout v0.4).
     */
    public function show(Garage $garage): GarageResource
    {
        abort_unless($garage->isPubliclyVisible(), 404);

        $garage->load([
            'openingHours',
            'images',
            'products' => fn ($query) => $query->where('status', ProductStatus::Approved),
            'services' => fn ($query) => $query->where('status', RepairServiceStatus::Approved)->where('is_active', true),
        ]);

        return new GarageResource($garage);
    }
}
