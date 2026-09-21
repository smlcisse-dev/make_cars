<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Garage;
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
            'sellable_type' => $this->sellable_type,
            'sellable_id' => $this->sellable_id,
            'user_id' => $this->user_id,
            'last_message_at' => $this->last_message_at,
            'sellable' => $this->whenLoaded('sellable', fn () => $this->sellable instanceof Garage
                ? new GarageResource($this->sellable)
                : new MarketSpaceAccountResource($this->sellable)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
        ];
    }
}
