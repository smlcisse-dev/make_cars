<?php

namespace App\Http\Resources;

use App\Models\ReactivationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de réactivation (CLAUDE.md §5, ajout v0.28), exposée au
 * professionnel concerné et à l'administrateur.
 *
 * @mixin ReactivationRequest
 */
class ReactivationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'message' => $this->message,
            'response_reason' => $this->response_reason,
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy ? ['id' => $this->decidedBy->id, 'name' => $this->decidedBy->name] : null),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
