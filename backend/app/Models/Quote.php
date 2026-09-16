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
 * Un devis est nécessaire dès qu'il y a une prestation de service à
 * réaliser, avec ou sans RDV préalable (CLAUDE.md §5 règle 10, ajout v0.9) :
 * `garage_id`/`user_id` référencent directement le garage et le client, le
 * RDV n'étant plus qu'un lien de traçabilité optionnel quand il existe. Un
 * seul Quote par Appointment quand ce lien existe (contrainte unique) : la
 * renégociation crée de nouvelles QuoteVersion au sein du même Quote, pas un
 * nouveau Quote.
 */
#[Fillable(['garage_id', 'user_id', 'appointment_id', 'status', 'paid_at'])]
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
     * @return BelongsTo<Garage, $this>
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * Client du devis (automobiliste, éventuellement un compte "express" —
     * CLAUDE.md §5, ajout v0.9).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Lien de traçabilité optionnel : un devis peut naître d'un RDV confirmé
     * ou être créé directement pour un client walk-in (CLAUDE.md §5, ajout
     * v0.9).
     *
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
