<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteDocumentType;
use App\Enums\QuoteStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\QuoteLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Montant facturé : lignes des factures de devis (`invoiced`) et des
 * commandes payées, datées par leur `paid_at`. Calcul unique, partagé par
 * les statistiques admin (toute la plateforme, CLAUDE.md §5 ajout v0.18) et
 * le tableau de bord d'un professionnel (un seul vendeur, ajout 2026-09-24).
 */
class InvoicedAmountService
{
    /**
     * @param  Garage|MarketSpaceAccount|null  $seller  null = toute la plateforme
     */
    public function total(
        ?CarbonInterface $periodStart,
        ?CarbonInterface $periodEnd,
        Garage|MarketSpaceAccount|null $seller = null,
    ): float {
        // Un Market Space n'émet jamais de devis (réparation réservée au garage).
        $quotesAmount = $seller instanceof MarketSpaceAccount ? 0.0 : (float) QuoteLine::query()
            ->join('quote_versions', 'quote_versions.id', '=', 'quote_lines.quote_version_id')
            ->join('quotes', 'quotes.id', '=', 'quote_versions.quote_id')
            ->where('quote_versions.document_type', QuoteDocumentType::Invoice->value)
            ->where('quotes.status', QuoteStatus::Invoiced->value)
            ->when($seller, fn ($q) => $q->where('quotes.garage_id', $seller->id))
            ->when($periodStart, fn ($q) => $q->where('quotes.paid_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('quotes.paid_at', '<=', $periodEnd))
            ->sum('quote_lines.line_total');

        $ordersAmount = (float) DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.status', OrderStatus::Paid->value)
            ->when($seller, fn ($q) => $q
                ->where('orders.sellable_type', $seller->getMorphClass())
                ->where('orders.sellable_id', $seller->id))
            ->when($periodStart, fn ($q) => $q->where('orders.paid_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('orders.paid_at', '<=', $periodEnd))
            ->sum('order_lines.line_total');

        return $quotesAmount + $ordersAmount;
    }

    /**
     * Même format que les autres montants de l'API (chaîne à deux décimales).
     */
    public static function format(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
