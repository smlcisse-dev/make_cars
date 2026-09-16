<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Premier maillon de la chaîne RDV → devis → validation → prestation →
 * paiement → facture (CLAUDE.md §5, règle 10 et ajout v0.7). La prise de RDV
 * ne déclenche jamais de paiement. Un RDV confirmé est le point d'ancrage du
 * futur devis : le module Devis référencera cet Appointment via une FK
 * `appointment_id` sur son propre modèle — la relation se construit dans
 * l'autre sens, rien à ajouter ici par anticipation.
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
}
