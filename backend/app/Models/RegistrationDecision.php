<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une décision de l'administrateur sur un dossier d'inscription (CLAUDE.md
 * §5 ajout v0.26, traçabilité §6) : `decision` vaut Approved ou Rejected,
 * `reason` n'est renseigné que pour un refus.
 */
#[Fillable(['decision', 'reason', 'decided_by', 'decided_at'])]
class RegistrationDecision extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => RegistrationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProfessionalRegistration, $this>
     */
    public function professionalRegistration(): BelongsTo
    {
        return $this->belongsTo(ProfessionalRegistration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
