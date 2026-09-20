<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DisputeResolutionAction;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Admin\RejectDisputeRequest;
use App\Http\Requests\Admin\RequestDisputeResponseRequest;
use App\Http\Requests\Admin\ResolveDisputeRequest;
use App\Http\Requests\Dispute\RespondDisputeRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Http\Resources\ReviewResource;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\DisputeAttachment;
use App\Models\Garage;
use App\Models\Order;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputeService) {}

    /**
     * Supervision admin en lecture, filtrable par statut (CLAUDE.md §5 règle
     * 8, ajout v0.11).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $disputes = Dispute::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['respondent', 'user'])
            ->latest()
            ->paginate();

        return DisputeResource::collection($disputes);
    }

    /**
     * Instruction du dossier : la transaction concernée, la conversation de
     * chat associée (Garage↔Automobiliste, quand elle existe) et les avis
     * déjà laissés sur le professionnel visé — tout l'historique lié
     * nécessaire pour trancher (CLAUDE.md §5, ajout v0.11).
     */
    public function show(Dispute $dispute): JsonResponse
    {
        $dispute->load(['respondent', 'transaction', 'user', 'attachments', 'messages.author', 'decidedBy', 'responseRequestedBy']);
        $dispute->transaction->load($dispute->transaction instanceof Order ? ['lines'] : ['versions.lines']);

        $conversation = $dispute->respondent instanceof Garage
            ? Conversation::where('garage_id', $dispute->respondent_id)->where('user_id', $dispute->user_id)->with('garage')->first()
            : null;

        $reviews = $dispute->respondent->reviews()->visible()->with('user')->latest()->get();

        return $this->success([
            'dispute' => new DisputeResource($dispute),
            'conversation' => $conversation ? new ConversationResource($conversation) : null,
            'reviews' => ReviewResource::collection($reviews),
        ]);
    }

    /**
     * Demande de réponse/défense au professionnel — optionnelle, l'admin
     * peut trancher directement si les preuves jointes suffisent.
     */
    public function requestResponse(RequestDisputeResponseRequest $request, Dispute $dispute): JsonResponse
    {
        abort_unless($dispute->status === DisputeStatus::Submitted, 403, 'Une réponse a déjà été demandée, ou le dossier est déjà tranché.');

        $dispute = $this->disputeService->requestResponse($dispute, $request->user(), $request->filled('message') ? $request->string('message')->toString() : null);

        return $this->success(new DisputeResource($dispute), 'Réponse demandée au professionnel.');
    }

    /**
     * Message libre de l'admin dans l'espace d'échange, en plus du message
     * optionnel de `requestResponse` — les deux parties peuvent s'exprimer à
     * plusieurs reprises avant la décision (CLAUDE.md §5, ajout v0.11).
     */
    public function respond(RespondDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        abort_if($dispute->isDecided(), 403, 'Cette réclamation est déjà tranchée.');

        $message = $this->disputeService->respond($dispute, $request->user(), $request->string('body')->toString());

        return $this->success(new DisputeMessageResource($message->load('author')), 'Message envoyé.', 201);
    }

    /**
     * Rejet (classement sans suite) — motif obligatoire, comme pour les
     * autres actions de modération (CLAUDE.md §5, ajout v0.11).
     */
    public function reject(RejectDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        abort_if($dispute->isDecided(), 403, 'Cette réclamation est déjà tranchée.');

        $dispute = $this->disputeService->resolveRejected($dispute, $request->user(), $request->string('reason')->toString());

        return $this->success(new DisputeResource($dispute), 'Réclamation rejetée.');
    }

    /**
     * Réclamation jugée fondée — motif obligatoire et action librement
     * choisie (avertissement ou suspension), aucune sanction automatique
     * (CLAUDE.md §5, ajout v0.11).
     */
    public function resolve(ResolveDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        abort_if($dispute->isDecided(), 403, 'Cette réclamation est déjà tranchée.');

        $dispute = $this->disputeService->resolveFounded(
            $dispute,
            $request->user(),
            $request->string('reason')->toString(),
            DisputeResolutionAction::from($request->string('action')->toString()),
        );

        return $this->success(new DisputeResource($dispute), 'Réclamation jugée fondée.');
    }

    /**
     * Clôture le dossier une fois la décision (et son éventuelle suite)
     * actée — pas de clôture automatique (CLAUDE.md §5, ajout v0.11).
     */
    public function close(Dispute $dispute): JsonResponse
    {
        abort_unless(
            in_array($dispute->status, [DisputeStatus::ResolvedFounded, DisputeStatus::ResolvedRejected], true),
            403,
            'Seule une réclamation déjà tranchée peut être clôturée.'
        );

        $dispute = $this->disputeService->close($dispute);

        return $this->success(new DisputeResource($dispute), 'Réclamation clôturée.');
    }

    public function downloadAttachment(Dispute $dispute, DisputeAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->dispute_id === $dispute->id, 404);

        return $attachment->download();
    }
}
