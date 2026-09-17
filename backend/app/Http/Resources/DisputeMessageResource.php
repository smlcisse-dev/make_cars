<?php

namespace App\Http\Resources;

use App\Models\DisputeMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DisputeMessage
 */
class DisputeMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->whenLoaded('author', fn () => new UserResource($this->author)),
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
