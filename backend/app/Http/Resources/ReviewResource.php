<?php

namespace App\Http\Resources;

use App\Models\Garage;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable_type' => $this->reviewable_type === Garage::class ? 'garage' : 'market_space',
            'reviewable_id' => $this->reviewable_id,
            'transaction_type' => $this->transaction_type === Order::class ? 'order' : 'quote',
            'transaction_id' => $this->transaction_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'moderation_reason' => $this->moderation_reason,
            'moderated_at' => $this->moderated_at,
            'client' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'created_at' => $this->created_at,
        ];
    }
}
