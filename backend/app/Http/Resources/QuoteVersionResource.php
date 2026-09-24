<?php

namespace App\Http\Resources;

use App\Models\QuoteVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteVersion
 */
class QuoteVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_id' => $this->quote_id,
            'version' => $this->version,
            'document_type' => $this->document_type,
            'is_sent' => $this->sent_at !== null,
            'sent_at' => $this->sent_at,
            'decision' => $this->decision,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at,
            'total' => $this->whenLoaded('lines', fn () => $this->total()),
            'lines' => QuoteLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
        ];
    }
}
