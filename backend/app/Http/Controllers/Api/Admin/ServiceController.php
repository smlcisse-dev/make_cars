<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Admin\RejectServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\RepairService;
use App\Services\RepairServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    public function __construct(private readonly RepairServiceService $serviceService) {}

    /**
     * Liste paginée, tous garages confondus — supervision admin (CLAUDE.md §5 règle 8).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RepairService::class);

        $services = RepairService::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with('garage')
            ->latest()
            ->paginate();

        return ServiceResource::collection($services);
    }

    public function show(RepairService $service): JsonResponse
    {
        Gate::authorize('view', $service);

        return $this->success(new ServiceResource($service->load('garage')));
    }

    public function approve(Request $request, RepairService $service): JsonResponse
    {
        Gate::authorize('review', $service);

        $service = $this->serviceService->approve($service, $request->user())->load('garage');

        return $this->success(new ServiceResource($service), 'Service validé.');
    }

    public function reject(RejectServiceRequest $request, RepairService $service): JsonResponse
    {
        $service = $this->serviceService->reject($service, $request->user(), $request->string('reason')->toString())->load('garage');

        return $this->success(new ServiceResource($service), 'Service rejeté.');
    }
}
