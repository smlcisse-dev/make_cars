<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Troisième niveau du découpage administratif (546 arrondissements),
 * rattaché à une commune (CLAUDE.md §5, ajout v0.19).
 */
#[Fillable(['commune_id', 'name', 'slug'])]
class Arrondissement extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
