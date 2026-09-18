<?php

namespace App\Http\Resources;

use App\Models\ProfessionalRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfessionalRegistration
 */
class ProfessionalRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_type' => $this->whenLoaded('user', fn () => $this->user->role->value),
            'account_type_label' => $this->whenLoaded('user', fn () => $this->user->role->label()),
            'structure_name' => $this->structure_name,
            'address' => $this->address,
            'business_registration_number' => $this->business_registration_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'is_suspended' => $this->isSuspended(),
            'suspension_reason' => $this->suspension_reason,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'documents' => RegistrationDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
