<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    /**
     * Supervision admin en lecture seule (CLAUDE.md §5 règle 8).
     */
    public function index(): AnonymousResourceCollection
    {
        $conversations = Conversation::query()
            ->with(['sellable', 'user'])
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->paginate();

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        return new ConversationResource($conversation->load(['sellable', 'user', 'messages.sender']));
    }
}
