<?php

namespace App\Http\Controllers\Api\MarketSpace;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedMarketSpaceAccount;
use App\Http\Requests\MarketSpace\UpdateOpeningHoursRequest;
use App\Http\Resources\MarketSpaceAccountResource;
use App\Services\MarketSpaceAccountService;
use Illuminate\Http\JsonResponse;

class OpeningHoursController extends Controller
{
    use ResolvesAuthenticatedMarketSpaceAccount;

    public function __construct(private readonly MarketSpaceAccountService $marketSpaceAccountService) {}

    public function update(UpdateOpeningHoursRequest $request): JsonResponse
    {
        $account = $this->marketSpaceAccountService->setOpeningHours(
            $this->authenticatedMarketSpaceAccount($request),
            $request->array('hours'),
        );

        return $this->success(new MarketSpaceAccountResource($account), 'Horaires mis à jour.');
    }
}
