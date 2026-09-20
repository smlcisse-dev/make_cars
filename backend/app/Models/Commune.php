<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deuxième niveau du découpage administratif (77 communes), rattachée à un
 * département (CLAUDE.md §5, ajout v0.19).
 */
#[Fillable(['department_id', 'name', 'slug'])]
class Commune extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Arrondissement, $this>
     */
    public function arrondissements(): HasMany
    {
        return $this->hasMany(Arrondissement::class);
    }
}
