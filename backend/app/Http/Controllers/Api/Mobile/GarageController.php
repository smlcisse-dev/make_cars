<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\ProductStatus;
use App\Enums\RepairServiceStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\GarageResource;
use App\Models\Garage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GarageController extends Controller
{
    /**
     * Liste des garages visibles publiquement (compte validé — CLAUDE.md §5
     * règle 4). La recherche géographique par proximité est un module à part
     * (carte, calcul de distance — cf. CLAUDE.md §7 points ouverts). Note
     * moyenne calculée via withAvg pour éviter le N+1 sur une liste (CLAUDE.md
     * §5, ajout v0.10) — utile au client pour choisir un garage.
     */
    public function index(): AnonymousResourceCollection
    {
        $garages = Garage::query()
            ->publiclyVisible()
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)])
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

        $garage->loadAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating');
        $garage->loadCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)]);
        $garage->load([
            'openingHours',
            'images',
            'department',
            'commune',
            'arrondissement',
            'products' => fn ($query) => $query->where('status', ProductStatus::Approved),
            'services' => fn ($query) => $query->where('status', RepairServiceStatus::Approved)->where('is_active', true),
        ]);

        return new GarageResource($garage);
    }
}
