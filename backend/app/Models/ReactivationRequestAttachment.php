<?php

namespace App\Models;

use Database\Factories\ReactivationRequestAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièce jointe (photo ou PDF) d'une demande de réactivation, sur le disque
 * privé dédié aux médias — jamais d'URL publique (CLAUDE.md §5, ajout
 * v0.28). Même principe que DisputeAttachment ; jamais supprimée.
 */
#[Fillable(['reactivation_request_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class ReactivationRequestAttachment extends Model
{
    /** @use HasFactory<ReactivationRequestAttachmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ReactivationRequest, $this>
     */
    public function reactivationRequest(): BelongsTo
    {
        return $this->belongsTo(ReactivationRequest::class);
    }

    /**
     * Le nom d'origine est rendu au téléchargement, pas le nom aléatoire du
     * fichier stocké.
     */
    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->original_name);
    }
}
