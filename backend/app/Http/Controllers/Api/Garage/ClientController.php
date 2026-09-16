<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Garage\StoreExpressClientRequest;
use App\Http\Resources\UserResource;
use App\Services\ExpressClientService;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function __construct(private readonly ExpressClientService $expressClientService) {}

    /**
     * Crée (ou rattache à un compte automobiliste existant, si l'email ou le
     * téléphone correspond déjà) un compte client "express" pour un client
     * présent physiquement sans app — préalable typique à la création d'un
     * devis sans RDV (CLAUDE.md §5, ajout v0.9).
     */
    public function storeExpress(StoreExpressClientRequest $request): JsonResponse
    {
        $client = $this->expressClientService->findOrCreateExpress($request->validated());

        return $this->success(new UserResource($client), 'Client rattaché.', $client->wasRecentlyCreated ? 201 : 200);
    }
}
