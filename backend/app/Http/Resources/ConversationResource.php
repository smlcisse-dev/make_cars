<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'garage_id' => $this->garage_id,
            'user_id' => $this->user_id,
            'last_message_at' => $this->last_message_at,
            'garage' => $this->whenLoaded('garage', fn () => new GarageResource($this->garage)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
        ];
    }
}
