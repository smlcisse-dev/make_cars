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
            'phone' => $this->phone,
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
