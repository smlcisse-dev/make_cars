<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\MarketSpaceOpeningHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['day_of_week', 'is_closed', 'opens_at', 'closes_at'])]
class MarketSpaceOpeningHour extends Model
{
    /** @use HasFactory<MarketSpaceOpeningHourFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MarketSpaceAccount, $this>
     */
    public function marketSpaceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketSpaceAccount::class);
    }
}
