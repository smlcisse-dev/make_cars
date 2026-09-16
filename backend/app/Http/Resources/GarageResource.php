<?php

namespace App\Http\Resources;

use App\Models\Garage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Garage
 */
class GarageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'opening_hours' => GarageOpeningHourResource::collection($this->whenLoaded('openingHours')),
            'images' => GarageImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
