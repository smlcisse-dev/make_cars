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
            'structure_name' => $this->structure_name,
            'address' => $this->address,
            'business_registration_number' => $this->business_registration_number,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'documents' => RegistrationDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
