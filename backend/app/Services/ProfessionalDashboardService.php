<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\QuoteStatus;
use App\Enums\RepairServiceStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Product;

/**
 * Chiffres clés du tableau de bord d'un professionnel (ajout 2026-09-24),
 * commun au Garage et au Market Space (vendeur polymorphe, comme Product et
 * Order). Tout est renvoyé en une seule réponse : avec la latence de la base
 * (Supabase), une requête HTTP par carte ralentirait l'écran d'autant.
 *
 * Les blocs propres à la réparation (RDV, devis, services) n'existent que
 * pour un garage : ils sont absents de la réponse d'un Market Space.
 */
class ProfessionalDashboardService
{
    /** Nombre de produits en stock bas détaillés dans la réponse. */
    private const LOW_STOCK_ITEMS_LIMIT = 5;

    public function __construct(private readonly InvoicedAmountService $invoicedAmountService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Garage|MarketSpaceAccount $seller): array
    {
        return [
            'structure_name' => $seller->name,
            'to_handle' => $this->toHandle($seller),
            'activity' => $this->activity($seller),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toHandle(Garage|MarketSpaceAccount $seller): array
    {
        $toHandle = [];

        if ($seller instanceof Garage) {
            $toHandle['pending_appointments'] = $seller->appointments()->where('status', AppointmentStatus::Pending)->count();
            $toHandle['quotes_to_start'] = $seller->quotes()->where('status', QuoteStatus::Accepted)->count();
            $toHandle['quotes_to_invoice'] = $seller->quotes()->where('status', QuoteStatus::InProgress)->count();
        }

        $toHandle['orders_to_collect'] = $seller->orders()->where('status', OrderStatus::Pending)->count();

        // Seuil configuré (pas de valeur par défaut, CLAUDE.md §5 ajout v0.12)
        // et stock inférieur ou égal : même règle que isAtOrBelowLowStockThreshold().
        $lowStock = $seller->products()
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');

        $toHandle['low_stock_products'] = [
            'count' => (clone $lowStock)->count(),
            'items' => $lowStock
                ->orderBy('stock_quantity')
                ->orderBy('name')
                ->limit(self::LOW_STOCK_ITEMS_LIMIT)
                ->get(['id', 'name', 'stock_quantity', 'low_stock_threshold'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                ])
                ->all(),
        ];

        if ($seller instanceof Garage) {
            $toHandle['rejected_services'] = $seller->services()->where('status', RepairServiceStatus::Rejected)->count();
        }

        $toHandle['rejected_products'] = $seller->products()->where('status', ProductStatus::Rejected)->count();
        $toHandle['open_disputes'] = $seller->disputes()
            ->whereIn('status', [DisputeStatus::Submitted, DisputeStatus::UnderReview])
            ->count();

        return $toHandle;
    }

    /**
     * @return array<string, mixed>
     */
    private function activity(Garage|MarketSpaceAccount $seller): array
    {
        // « Mois en cours » au sens du professionnel (heure du Bénin), converti
        // dans le fuseau de stockage des dates (`paid_at`, UTC).
        $monthStart = now(config('geo.business_timezone'))->startOfMonth()->setTimezone(config('app.timezone'));

        $visibleReviews = $seller->reviews()->visible();
        $reviewsCount = (clone $visibleReviews)->count();
        $averageRating = $reviewsCount > 0 ? (clone $visibleReviews)->avg('rating') : null;

        $activity = [
            'month_start' => $monthStart->copy()->setTimezone(config('geo.business_timezone'))->toDateString(),
            'invoiced_amount_this_month' => InvoicedAmountService::format(
                $this->invoicedAmountService->total($monthStart, null, $seller)
            ),
            'reviews' => [
                'count' => $reviewsCount,
                'average_rating' => $averageRating !== null ? round((float) $averageRating, 1) : null,
            ],
        ];

        if ($seller instanceof Garage) {
            $activity['quotes_awaiting_client'] = $seller->quotes()
                ->whereIn('status', [QuoteStatus::Sent, QuoteStatus::Negotiating])
                ->count();
            $activity['pending_services'] = $seller->services()->where('status', RepairServiceStatus::Pending)->count();
        }

        $activity['pending_products'] = $seller->products()->where('status', ProductStatus::Pending)->count();

        return $activity;
    }
}
