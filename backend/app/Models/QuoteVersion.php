<?php

namespace App\Models;

use App\Enums\QuoteDocumentType;
use App\Enums\QuoteVersionDecision;
use Database\Factories\QuoteVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un instantané figé du devis à un tour de négociation donné : lignes,
 * prix et libellés snapshotés à la création (contrairement à l'en-tête
 * garage/client, toujours lu depuis les relations en direct — CLAUDE.md §5,
 * ajout v0.8). Chaque version régénère son propre PDF, jamais réécrit.
 */
#[Fillable(['quote_id', 'version', 'document_type', 'pdf_disk', 'pdf_path', 'sent_at', 'decision', 'decided_at', 'decided_by'])]
class QuoteVersion extends Model
{
    /** @use HasFactory<QuoteVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => QuoteDocumentType::class,
            'decision' => QuoteVersionDecision::class,
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function total(): string
    {
        return (string) $this->lines->sum(fn (QuoteLine $line) => $line->line_total);
    }
}
