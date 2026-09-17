<?php

namespace App\Models;

use Database\Factories\MarketSpaceImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['disk', 'path', 'position'])]
class MarketSpaceImage extends Model
{
    /** @use HasFactory<MarketSpaceImageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MarketSpaceAccount, $this>
     */
    public function marketSpaceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketSpaceAccount::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
