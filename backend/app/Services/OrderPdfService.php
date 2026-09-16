<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Génère et stocke la facture PDF d'une commande — même mécanisme que
 * QuotePdfService (CLAUDE.md §5, ajout v0.9), mais un seul document par
 * commande (pas de versioning : le prix est déjà fixé au catalogue, aucune
 * négociation). L'en-tête vendeur (Garage ou Market Space) est lu depuis la
 * relation polymorphe `sellable` en direct, jamais dupliqué en base.
 */
class OrderPdfService
{
    private function disk(): string
    {
        return config('filesystems.private_media_disk', 'local');
    }

    public function generate(Order $order): Order
    {
        $order->loadMissing('sellable', 'user', 'lines');
        $total = $order->lines->sum(fn ($line) => (float) $line->line_total);

        $pdf = Pdf::loadView('pdf.order', [
            'order' => $order,
            'seller' => $order->sellable,
            'client' => $order->user,
            'lines' => $order->lines,
            'total' => $total,
        ]);

        $disk = $this->disk();
        $path = "orders/{$order->id}/invoice.pdf";
        Storage::disk($disk)->put($path, $pdf->output());

        $order->update(['pdf_disk' => $disk, 'pdf_path' => $path]);

        return $order;
    }
}
