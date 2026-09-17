<?php

namespace App\Http\Controllers\Api\MarketSpace;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedMarketSpaceAccount;
use App\Http\Requests\Dispute\RespondDisputeRequest;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\DisputeAttachment;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le Market Space consulte les réclamations le concernant et peut y
 * répondre via l'espace d'échange dédié — jamais de décision de son côté,
 * réservée à l'admin (CLAUDE.md §5, ajout v0.11).
 */
class DisputeController extends Controller
{
    use ResolvesAuthenticatedMarketSpaceAccount;

    public function __construct(private readonly DisputeService $disputeService) {}

    private function authorizeDispute(Request $request, Dispute $dispute): void
    {
        $account = $this->authenticatedMarketSpaceAccount($request);
        abort_unless($dispute->respondent_type === $account->getMorphClass() && $dispute->respondent_id === $account->id, 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $disputes = $this->authenticatedMarketSpaceAccount($request)->disputes()->latest()->paginate();

        return DisputeResource::collection($disputes);
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorizeDispute($request, $dispute);

        return $this->success(new DisputeResource($dispute->load(['attachments', 'messages.author', 'user'])));
    }

    public function respond(RespondDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        $this->authorizeDispute($request, $dispute);
        abort_if($dispute->isDecided(), 403, 'Cette réclamation est déjà tranchée.');

        $message = $this->disputeService->respond($dispute, $request->user(), $request->string('body')->toString());

        return $this->success(new DisputeMessageResource($message->load('author')), 'Réponse envoyée.', 201);
    }

    public function downloadAttachment(Request $request, Dispute $dispute, DisputeAttachment $attachment): StreamedResponse
    {
        $this->authorizeDispute($request, $dispute);
        abort_unless($attachment->dispute_id === $dispute->id, 404);

        return $attachment->download();
    }
}
