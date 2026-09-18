<?php

namespace App\Http\Resources;

use App\Models\RepairService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RepairService
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'garage_id' => $this->garage_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'price' => $this->price,
            'duration_minutes' => $this->duration_minutes,
            'image_url' => $this->imageUrl(),
            'is_active' => $this->is_active,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'garage' => $this->whenLoaded('garage', fn () => new GarageResource($this->garage)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
