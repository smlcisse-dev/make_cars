<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\QuoteStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Premier maillon de la chaîne RDV → devis → validation → prestation →
 * paiement → facture (CLAUDE.md §5, règle 10 et ajouts v0.7/v0.8). La prise
 * de RDV ne déclenche jamais de paiement. `status` devient "completed" dans
 * les deux issues possibles (prestation facturée ou négociation infructueuse)
 * — la distinction se lit via `quote->status` (Invoiced vs Abandoned),
 * jamais dupliquée ici.
 */
#[Fillable(['garage_id', 'user_id', 'repair_service_id', 'description', 'requested_at', 'proposed_at', 'confirmed_at', 'status', 'rejection_reason'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'proposed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Garage, $this>
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<RepairService, $this>
     */
    public function repairService(): BelongsTo
    {
        return $this->belongsTo(RepairService::class);
    }

    /**
     * @return HasOne<Quote, $this>
     */
    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    /**
     * "Terminé avec prestation réalisée" (facturé) vs "terminé sans suite"
     * (négociation infructueuse) — utile pour les statistiques agrégées
     * (CLAUDE.md §1). Null tant que le RDV n'est pas "completed".
     */
    public function wasCompletedWithService(): ?bool
    {
        if ($this->status !== AppointmentStatus::Completed) {
            return null;
        }

        return $this->quote?->status === QuoteStatus::Invoiced;
    }
}
