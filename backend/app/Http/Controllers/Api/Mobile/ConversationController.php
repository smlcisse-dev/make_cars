<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Mobile\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Garage;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = $request->user()->conversations()
            ->with('garage')
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->paginate();

        return ConversationResource::collection($conversations);
    }

    /**
     * Un automobiliste peut contacter un garage à tout moment, y compris
     * sans RDV existant (panne d'urgence) — CLAUDE.md §5, ajout v0.8.
     * Idempotent : une seule conversation par paire (garage, automobiliste).
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $garage = Garage::find($request->integer('garage_id'));
        abort_unless($garage && $garage->isPubliclyVisible(), 404, 'Garage introuvable.');

        $conversation = $this->chatService->findOrCreateConversation($garage, $request->user());

        return $this->success(new ConversationResource($conversation->load('garage')), 'Conversation prête.', 201);
    }

    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorizeConversation($request, $conversation);

        $messages = $conversation->messages()->with('sender')->latest()->paginate();

        return MessageResource::collection($messages);
    }

    public function sendMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $message = $this->chatService->sendMessage($conversation, $request->user(), $request->validated(), $request->file('image'));

        return $this->success(new MessageResource($message), 'Message envoyé.', 201);
    }

    public function downloadImage(Request $request, Conversation $conversation, Message $message): StreamedResponse
    {
        $this->authorizeConversation($request, $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->hasImage(), 404);

        return $message->streamImage();
    }
}
