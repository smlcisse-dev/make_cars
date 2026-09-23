<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\BuildsProfessionalProfileMeta;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Requests\Garage\UpdateGarageProfileRequest;
use App\Http\Resources\GarageResource;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use BuildsProfessionalProfileMeta, ResolvesAuthenticatedGarage;

    public function __construct(private readonly GarageService $garageService) {}

    public function show(Request $request): JsonResponse
    {
        $garage = $this->authenticatedGarage($request)->load(['openingHours', 'images', 'department', 'commune', 'arrondissement']);

        return $this->success(new GarageResource($garage), meta: $this->professionalProfileMeta($garage, $request->user()->professionalRegistration));
    }

    public function update(UpdateGarageProfileRequest $request): JsonResponse
    {
        $garage = $this->garageService->update(
            $this->authenticatedGarage($request),
            $request->validated(),
        );

        return $this->success(new GarageResource($garage->load(['openingHours', 'images', 'department', 'commune', 'arrondissement'])), 'Profil garage mis à jour.', meta: $this->professionalProfileMeta($garage, $request->user()->professionalRegistration));
    }
}
