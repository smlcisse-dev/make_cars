<?php

namespace App\Http\Resources;

use App\Models\Garage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            // Vendeur (nom), quand la relation est chargée — supervision admin.
            'seller' => $this->whenLoaded('sellable', fn () => $this->sellable ? [
                'id' => $this->sellable->id,
                'name' => $this->sellable->name,
            ] : null),
            'user_id' => $this->user_id,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'total' => $this->whenLoaded('lines', fn () => $this->total()),
            'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
            'client' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
