<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une conversation regroupe tous les échanges entre UN garage ou UNE
 * boutique Market Space (`sellable`, relation polymorphe comme `Product`/
 * `Order`) et UN automobiliste, indépendamment de tout RDV précis
 * (contrainte unique sur le trio) — un automobiliste peut contacter un
 * vendeur à tout moment, y compris sans RDV (ex. panne d'urgence). Jamais de
 * messagerie de groupe, jamais de contact entre automobilistes ou entre
 * professionnels (CLAUDE.md §5, ajout v0.8).
 */
#[Fillable(['sellable_type', 'sellable_id', 'user_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sellable(): MorphTo
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
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
