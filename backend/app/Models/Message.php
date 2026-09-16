<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Message texte et/ou image d'une conversation, ou message système posté
 * automatiquement lors de la génération d'un devis/facture (CLAUDE.md §5,
 * ajout v0.8) — `sender_id` null identifie un message système.
 */
#[Fillable(['conversation_id', 'sender_id', 'body', 'image_disk', 'image_path', 'attachment_type', 'quote_version_id'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const ATTACHMENT_QUOTE_PDF = 'quote_pdf';

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<QuoteVersion, $this>
     */
    public function quoteVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class);
    }

    public function isSystemMessage(): bool
    {
        return $this->sender_id === null;
    }

    public function hasImage(): bool
    {
        return $this->image_path !== null;
    }

    public function streamImage()
    {
        return Storage::disk($this->image_disk)->download($this->image_path);
    }
}
