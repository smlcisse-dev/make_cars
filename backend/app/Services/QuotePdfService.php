<?php

namespace App\Services;

use App\Models\QuoteLine;
use App\Models\QuoteVersion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Génère et stocke le PDF d'une version de devis/facture. L'en-tête (garage,
 * client) est lu depuis les relations en direct au moment du rendu — jamais
 * dupliqué en base (CLAUDE.md §5, ajout v0.8).
 */
class QuotePdfService
{
    private function disk(): string
    {
        return config('filesystems.private_media_disk', 'local');
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines
     */
    public function generate(QuoteVersion $quoteVersion): QuoteVersion
    {
        $quote = $quoteVersion->quote()->with('appointment.garage', 'appointment.user')->first();
        $appointment = $quote->appointment;
        $lines = $quoteVersion->lines()->get();
        $total = $lines->sum(fn ($line) => (float) $line->line_total);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'quoteVersion' => $quoteVersion,
            'garage' => $appointment->garage,
            'client' => $appointment->user,
            'lines' => $lines,
            'total' => $total,
            'documentLabel' => $quoteVersion->document_type->value === 'invoice' ? 'Facture' : 'Devis',
        ]);

        $disk = $this->disk();
        $path = "quotes/{$quote->id}/v{$quoteVersion->version}-{$quoteVersion->document_type->value}.pdf";
        Storage::disk($disk)->put($path, $pdf->output());

        $quoteVersion->update(['pdf_disk' => $disk, 'pdf_path' => $path]);

        return $quoteVersion;
    }
}
