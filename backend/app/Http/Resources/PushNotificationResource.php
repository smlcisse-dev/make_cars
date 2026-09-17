<?php

namespace App\Http\Resources;

use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PushNotification
 */
class PushNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'status' => $this->status,
            'read_at' => $this->read_at,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
