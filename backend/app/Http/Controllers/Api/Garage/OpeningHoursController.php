<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Requests\Garage\UpdateOpeningHoursRequest;
use App\Http\Resources\GarageResource;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;

class OpeningHoursController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly GarageService $garageService) {}

    public function update(UpdateOpeningHoursRequest $request): JsonResponse
    {
        $garage = $this->garageService->setOpeningHours(
            $this->authenticatedGarage($request),
            $request->array('hours'),
        );

        return $this->success(new GarageResource($garage), 'Horaires mis à jour.');
    }
}
