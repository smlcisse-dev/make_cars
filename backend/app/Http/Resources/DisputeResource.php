<?php

namespace App\Http\Resources;

use App\Models\Dispute;
use App\Models\Garage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'respondent_type' => $this->respondent_type === Garage::class ? 'garage' : 'market_space',
            'respondent_id' => $this->respondent_id,
            // Cible réelle (nom, ville...) pour l'affichage admin — même
            // logique que ReviewResource->reviewable, jamais dupliquée en base.
            'respondent' => $this->whenLoaded('respondent', fn () => $this->respondent_type === Garage::class
                ? new GarageResource($this->respondent)
                : new MarketSpaceAccountResource($this->respondent)),
            'transaction_type' => $this->transaction_type === Order::class ? 'order' : 'quote',
            'transaction_id' => $this->transaction_id,
            'transaction' => $this->whenLoaded('transaction', fn () => $this->transaction_type === Order::class
                ? new OrderResource($this->transaction)
                : new QuoteResource($this->transaction)),
            'reason' => $this->reason,
            'status' => $this->status,
            'response_requested_at' => $this->response_requested_at,
            'resolution_reason' => $this->resolution_reason,
            'resolution_action' => $this->resolution_action,
            'decided_at' => $this->decided_at,
            'closed_at' => $this->closed_at,
            'client' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy ? new UserResource($this->decidedBy) : null),
            'attachments' => DisputeAttachmentResource::collection($this->whenLoaded('attachments')),
            'messages' => DisputeMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
        ];
    }
}
