<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Mobile\StoreDisputeRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\DisputeAttachment;
use App\Models\Order;
use App\Models\Quote;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputeService) {}

    private function authorizeDispute(Request $request, Dispute $dispute): void
    {
        abort_unless($dispute->user_id === $request->user()->id, 404);
    }

    /**
     * Réclamations déposées par l'automobiliste authentifié, tous
     * garages/boutiques confondus — lui permet de suivre leur statut.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $disputes = $request->user()->disputes()->latest()->paginate();

        return DisputeResource::collection($disputes);
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorizeDispute($request, $dispute);

        return $this->success(new DisputeResource($dispute->load(['attachments', 'messages.author'])));
    }

    /**
     * Un devis facturé peut faire l'objet d'une réclamation (CLAUDE.md §5,
     * ajout v0.11).
     */
    public function storeForQuote(StoreDisputeRequest $request, Quote $quote): JsonResponse
    {
        $dispute = $this->disputeService->createForQuote($quote, $request->user(), $request->validated(), $request->file('photos') ?? []);

        return $this->success(new DisputeResource($dispute), 'Réclamation déposée.', 201);
    }

    /**
     * Une commande payée peut faire l'objet d'une réclamation (CLAUDE.md §5,
     * ajout v0.11).
     */
    public function storeForOrder(StoreDisputeRequest $request, Order $order): JsonResponse
    {
        $dispute = $this->disputeService->createForOrder($order, $request->user(), $request->validated(), $request->file('photos') ?? []);

        return $this->success(new DisputeResource($dispute), 'Réclamation déposée.', 201);
    }

    public function downloadAttachment(Request $request, Dispute $dispute, DisputeAttachment $attachment): StreamedResponse
    {
        $this->authorizeDispute($request, $dispute);
        abort_unless($attachment->dispute_id === $dispute->id, 404);

        return $attachment->download();
    }
}
