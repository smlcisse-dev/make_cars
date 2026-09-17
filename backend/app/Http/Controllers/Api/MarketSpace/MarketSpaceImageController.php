<?php

namespace App\Http\Controllers\Api\MarketSpace;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedMarketSpaceAccount;
use App\Http\Requests\MarketSpace\UploadMarketSpaceImagesRequest;
use App\Http\Resources\MarketSpaceImageResource;
use App\Models\MarketSpaceImage;
use App\Services\MarketSpaceAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketSpaceImageController extends Controller
{
    use ResolvesAuthenticatedMarketSpaceAccount;

    public function __construct(private readonly MarketSpaceAccountService $marketSpaceAccountService) {}

    public function store(UploadMarketSpaceImagesRequest $request): JsonResponse
    {
        $account = $this->authenticatedMarketSpaceAccount($request);

        $images = collect($request->file('images'))
            ->map(fn ($file) => $this->marketSpaceAccountService->addImage($account, $file));

        return $this->success(MarketSpaceImageResource::collection($images), 'Photo(s) ajoutée(s).', 201);
    }

    public function destroy(Request $request, MarketSpaceImage $image): JsonResponse
    {
        $account = $this->authenticatedMarketSpaceAccount($request);

        abort_unless($image->market_space_account_id === $account->id, 404);

        $this->marketSpaceAccountService->removeImage($image);

        return $this->success(message: 'Photo supprimée.');
    }
}
