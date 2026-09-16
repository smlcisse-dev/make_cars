<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Requests\Garage\UploadGarageImagesRequest;
use App\Http\Resources\GarageImageResource;
use App\Models\GarageImage;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GarageImageController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly GarageService $garageService) {}

    public function store(UploadGarageImagesRequest $request): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);

        $images = collect($request->file('images'))
            ->map(fn ($file) => $this->garageService->addImage($garage, $file));

        return $this->success(GarageImageResource::collection($images), 'Photo(s) ajoutée(s).', 201);
    }

    public function destroy(Request $request, GarageImage $image): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);

        abort_unless($image->garage_id === $garage->id, 404);

        $this->garageService->removeImage($image);

        return $this->success(message: 'Photo supprimée.');
    }
}
