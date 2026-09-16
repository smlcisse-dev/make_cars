<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Enums\QuoteVersionDecision;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Deuxième maillon de la chaîne RDV → devis → validation → prestation →
 * paiement → facture (CLAUDE.md §5 règle 10, ajout v0.8). Un seul Quote par
 * Appointment (contrainte unique) : la renégociation crée de nouvelles
 * QuoteVersion au sein du même Quote, pas un nouveau Quote.
 */
#[Fillable(['appointment_id', 'status', 'paid_at'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return HasMany<QuoteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class);
    }

    /**
     * Dernière version en date, sans pointeur dénormalisé à maintenir.
     *
     * @return HasOne<QuoteVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(QuoteVersion::class)->latestOfMany('version');
    }

    /**
     * Version validée par le client — au plus une par devis, puisqu'une fois
     * acceptée plus aucune négociation n'a lieu (CLAUDE.md §5, ajout v0.8).
     * Sert de base aux lignes copiées lors de la génération de la facture.
     *
     * @return HasOne<QuoteVersion, $this>
     */
    public function acceptedVersion(): HasOne
    {
        return $this->hasOne(QuoteVersion::class)->where('decision', QuoteVersionDecision::Accepted);
    }
}
