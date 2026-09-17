<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Avis d'un automobiliste sur un Garage ou un Market Space, laissé
 * uniquement après une transaction terminée (CLAUDE.md §5, règle 7 et ajout
 * v0.10). "reviewable" porte la cible notée (Garage/MarketSpaceAccount),
 * "transaction" porte la preuve d'éligibilité (Quote facturé/Order payé) et
 * garantit, via une contrainte unique, un seul avis par transaction.
 */
#[Fillable(['reviewable_type', 'reviewable_id', 'transaction_type', 'transaction_id', 'user_id', 'rating', 'comment', 'status', 'moderation_reason', 'moderated_by', 'moderated_at'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Transaction ayant rendu cet avis éligible (Quote facturé ou Order
     * payé) — jamais modifiable une fois l'avis créé.
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
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Visible);
    }
}
