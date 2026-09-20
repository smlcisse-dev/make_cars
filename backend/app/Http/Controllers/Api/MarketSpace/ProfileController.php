<?php

namespace App\Http\Controllers\Api\MarketSpace;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedMarketSpaceAccount;
use App\Http\Requests\MarketSpace\UpdateMarketSpaceProfileRequest;
use App\Http\Resources\MarketSpaceAccountResource;
use App\Services\MarketSpaceAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ResolvesAuthenticatedMarketSpaceAccount;

    public function __construct(private readonly MarketSpaceAccountService $marketSpaceAccountService) {}

    public function show(Request $request): JsonResponse
    {
        $account = $this->authenticatedMarketSpaceAccount($request)->load(['openingHours', 'images', 'department', 'commune', 'arrondissement']);

        return $this->success(new MarketSpaceAccountResource($account));
    }

    public function update(UpdateMarketSpaceProfileRequest $request): JsonResponse
    {
        $account = $this->marketSpaceAccountService->update(
            $this->authenticatedMarketSpaceAccount($request),
            $request->validated(),
        );

        return $this->success(new MarketSpaceAccountResource($account->load(['openingHours', 'images', 'department', 'commune', 'arrondissement'])), 'Profil Market Space mis à jour.');
    }
}
