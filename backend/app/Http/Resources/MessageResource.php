<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'is_system' => $this->isSystemMessage(),
            'sender' => $this->whenLoaded('sender', fn () => $this->sender ? new UserResource($this->sender) : null),
            'body' => $this->body,
            'has_image' => $this->hasImage(),
            'attachment_type' => $this->attachment_type,
            'quote_version_id' => $this->quote_version_id,
            // Pas de whenLoaded : la relation est chargée à la demande, jamais absente par oubli de `with()`.
            'quote_id' => $this->when($this->quote_version_id !== null, fn () => $this->quoteVersion?->quote_id),
            'created_at' => $this->created_at,
        ];
    }
}
