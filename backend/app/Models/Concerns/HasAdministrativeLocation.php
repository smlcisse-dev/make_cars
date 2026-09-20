<?php

namespace App\Models\Concerns;

use App\Models\Arrondissement;
use App\Models\Commune;
use App\Models\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Localisation structurée d'un profil professionnel : Département → Commune
 * → Arrondissement, plus un quartier en texte libre (`neighborhood`).
 * Mutualisé entre Garage et MarketSpaceAccount (CLAUDE.md §5, ajout v0.19).
 */
trait HasAdministrativeLocation
{
    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return BelongsTo<Arrondissement, $this>
     */
    public function arrondissement(): BelongsTo
    {
        return $this->belongsTo(Arrondissement::class);
    }
}
