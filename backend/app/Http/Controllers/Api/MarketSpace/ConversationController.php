<?php

namespace App\Http\Controllers\Api\MarketSpace;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedMarketSpaceAccount;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    use ResolvesAuthenticatedMarketSpaceAccount;

    public function __construct(private readonly ChatService $chatService) {}

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $account = $this->authenticatedMarketSpaceAccount($request);
        abort_unless($conversation->sellable_type === $account->getMorphClass() && $conversation->sellable_id === $account->id, 404);
    }

    /**
     * Le garagiste voit les conversations déjà initiées par des
     * automobilistes — pas de création côté garage (CLAUDE.md §5, ajout v0.8).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->authenticatedMarketSpaceAccount($request)->conversations()
            ->with('user')
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->paginate();

        return ConversationResource::collection($conversations);
    }

    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorizeConversation($request, $conversation);

        $messages = $conversation->messages()->with(['sender', 'quoteVersion'])->latest()->paginate();

        return MessageResource::collection($messages);
    }

    public function sendMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $message = $this->chatService->sendMessage($conversation, $request->user(), $request->validated(), $request->file('image'));

        return $this->success(new MessageResource($message->load('sender')), 'Message envoyé.', 201);
    }

    public function downloadImage(Request $request, Conversation $conversation, Message $message): StreamedResponse
    {
        $this->authorizeConversation($request, $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->hasImage(), 404);

        return $message->streamImage();
    }
}
