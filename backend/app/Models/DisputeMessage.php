<?php

namespace App\Models;

use Database\Factories\DisputeMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message de l'espace d'échange dédié à une réclamation, entre l'admin et le
 * professionnel concerné — distinct du chat Garage↔Automobiliste
 * (CLAUDE.md §5, ajout v0.11). `author_id` identifie toujours un utilisateur
 * réel (admin ou professionnel), jamais de message système ici.
 */
#[Fillable(['dispute_id', 'author_id', 'body'])]
class DisputeMessage extends Model
{
    /** @use HasFactory<DisputeMessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Dispute, $this>
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
