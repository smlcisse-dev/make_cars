<?php

namespace App\Http\Resources;

use App\Models\MarketSpaceAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MarketSpaceAccount
 */
class MarketSpaceAccountResource extends JsonResource
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
            'city' => $this->city?->value,
            'city_label' => $this->city?->label(),
            'region' => $this->region?->value,
            'region_label' => $this->region?->label(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'average_rating' => $this->average_rating !== null ? round((float) $this->average_rating, 1) : null,
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'opening_hours' => MarketSpaceOpeningHourResource::collection($this->whenLoaded('openingHours')),
            'images' => MarketSpaceImageResource::collection($this->whenLoaded('images')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
