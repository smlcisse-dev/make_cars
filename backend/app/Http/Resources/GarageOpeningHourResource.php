<?php

namespace App\Http\Resources;

use App\Models\GarageOpeningHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GarageOpeningHour
 */
class GarageOpeningHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week->value,
            'day_label' => $this->day_of_week->label(),
            'is_closed' => $this->is_closed,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
        ];
    }
}
