<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
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
            'repair_service_id' => $this->repair_service_id,
            'description' => $this->description,
            'requested_at' => $this->requested_at,
            'proposed_at' => $this->proposed_at,
            'confirmed_at' => $this->confirmed_at,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'garage' => $this->whenLoaded('garage', fn () => new GarageResource($this->garage)),
            'repair_service' => $this->whenLoaded('repairService', fn () => $this->repairService ? new ServiceResource($this->repairService) : null),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
