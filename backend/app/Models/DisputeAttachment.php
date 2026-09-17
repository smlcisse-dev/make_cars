<?php

namespace App\Models;

use Database\Factories\DisputeAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photo jointe en preuve à une réclamation, sur le disque privé dédié aux
 * médias — jamais d'URL publique (CLAUDE.md §5, ajout v0.11).
 */
#[Fillable(['dispute_id', 'disk', 'path', 'position'])]
class DisputeAttachment extends Model
{
    /** @use HasFactory<DisputeAttachmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Dispute, $this>
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path);
    }
}
