<?php

namespace App\Models;

use App\Enums\ReactivationRequestStatus;
use Database\Factories\ReactivationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de réactivation d'un compte suspendu, envoyée par le professionnel
 * (CLAUDE.md §5, ajout v0.28). Seul l'administrateur décide : il réactive le
 * compte (demande `accepted`) ou refuse la demande avec un motif
 * (`refused`, le compte reste suspendu). Jamais supprimée.
 */
#[Fillable(['message', 'status', 'response_reason', 'decided_by', 'decided_at'])]
class ReactivationRequest extends Model
{
    /** @use HasFactory<ReactivationRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReactivationRequestStatus::class,
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
