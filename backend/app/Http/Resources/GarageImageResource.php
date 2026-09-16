<?php

namespace App\Http\Resources;

use App\Models\GarageImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GarageImage
 */
class GarageImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'position' => $this->position,
        ];
    }
}
