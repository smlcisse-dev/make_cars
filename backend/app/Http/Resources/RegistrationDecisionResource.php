<?php

namespace App\Http\Resources;

use App\Models\RegistrationDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegistrationDecision
 */
class RegistrationDecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decision' => $this->decision->value,
            'decision_label' => $this->decision->label(),
            'reason' => $this->reason,
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy ? ['id' => $this->decidedBy->id, 'name' => $this->decidedBy->name] : null),
            'decided_at' => $this->decided_at->toIso8601String(),
        ];
    }
}
