<?php

namespace App\Http\Resources;

use App\Models\Garage;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sellable_type' => $this->sellable_type === Garage::class ? 'garage' : 'market_space',
            'sellable_id' => $this->sellable_id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
