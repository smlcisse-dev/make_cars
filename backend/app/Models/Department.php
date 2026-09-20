<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Premier niveau du découpage administratif (12 départements du Bénin) —
 * donnée de référence en lecture seule, chargée par LocationSeeder
 * (CLAUDE.md §5, ajout v0.19).
 */
#[Fillable(['name', 'slug'])]
class Department extends Model
{
    public $timestamps = false;

    /**
     * @return HasMany<Commune, $this>
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
