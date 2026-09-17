<?php

namespace App\Models;

use App\Enums\DisputeResolutionAction;
use App\Enums\DisputeStatus;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Réclamation d'un automobiliste, obligatoirement rattachée à une
 * transaction terminée (devis facturé ou commande payée) — CLAUDE.md §5,
 * ajout v0.11. "respondent" porte le professionnel visé (Garage ou
 * MarketSpaceAccount), "transaction" porte la preuve d'éligibilité (Quote
 * ou Order), même principe que le module Avis.
 */
#[Fillable(['respondent_type', 'respondent_id', 'transaction_type', 'transaction_id', 'user_id', 'reason', 'status', 'response_requested_at', 'response_requested_by', 'resolution_reason', 'resolution_action', 'decided_by', 'decided_at', 'closed_at'])]
class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'resolution_action' => DisputeResolutionAction::class,
            'response_requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function respondent(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Transaction ayant rendu cette réclamation éligible (Quote facturé ou
     * Order payé).
     *
     * @return MorphTo<Model, $this>
     */
    public function transaction(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responseRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'response_requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<DisputeAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(DisputeAttachment::class)->orderBy('position');
    }

    /**
     * Espace d'échange dédié entre l'admin et le professionnel concerné.
     *
     * @return HasMany<DisputeMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class);
    }

    /**
     * Une fois tranchée (fondée/rejetée) ou clôturée, plus aucune nouvelle
     * décision n'est possible.
     */
    public function isDecided(): bool
    {
        return in_array($this->status, [
            DisputeStatus::ResolvedFounded,
            DisputeStatus::ResolvedRejected,
            DisputeStatus::Closed,
        ], true);
    }
}
