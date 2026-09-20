<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\GarageResource;
use App\Models\Garage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GarageController extends Controller
{
    /**
     * Supervision admin : accès en lecture à tous les garages, validés ou non
     * (CLAUDE.md §5, règle 8).
     */
    public function index(): AnonymousResourceCollection
    {
        $garages = Garage::query()->with(['user', 'openingHours', 'images'])->paginate();

        return GarageResource::collection($garages);
    }

    public function show(Garage $garage): GarageResource
    {
        return new GarageResource($garage->load(['openingHours', 'images', 'department', 'commune', 'arrondissement']));
    }
}
