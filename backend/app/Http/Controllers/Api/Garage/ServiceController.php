<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceAvailabilityRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\RepairService;
use App\Services\RepairServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly RepairServiceService $serviceService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $services = $this->authenticatedGarage($request)->services()->latest()->paginate();

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->serviceService->create(
            $this->authenticatedGarage($request),
            $request->safe()->except('image'),
            $request->file('image'),
        );

        return $this->success(new ServiceResource($service), 'Service ajouté, en attente de validation admin.', 201);
    }

    public function update(UpdateServiceRequest $request, RepairService $service): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($service->garage_id === $garage->id, 404);

        $service = $this->serviceService->update($service, $request->safe()->except('image'), $request->file('image'));

        return $this->success(new ServiceResource($service), 'Service mis à jour, en attente de validation admin.');
    }

    public function updateAvailability(UpdateServiceAvailabilityRequest $request, RepairService $service): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($service->garage_id === $garage->id, 404);

        $service = $this->serviceService->updateAvailability($service, $request->boolean('is_active'));

        return $this->success(new ServiceResource($service), 'Disponibilité mise à jour.');
    }

    public function destroy(Request $request, RepairService $service): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($service->garage_id === $garage->id, 404);

        $this->serviceService->delete($service);

        return $this->success(message: 'Service supprimé.');
    }
}
