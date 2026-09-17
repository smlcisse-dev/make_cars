<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\City;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\QuoteDocumentType;
use App\Enums\QuoteStatus;
use App\Enums\Region;
use App\Enums\RegistrationStatus;
use App\Models\Appointment;
use App\Models\Dispute;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Review;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques agrégées pour l'administrateur, destinées à appuyer les
 * politiques de régulation/formalisation du secteur auprès des autorités
 * béninoises (CLAUDE.md §1, ajout v0.17). Calcul en mémoire/SQL simple sur
 * l'ensemble des données, à l'échelle actuelle — même principe de
 * performance-acceptable-en-V1 que GeoSearchService (CLAUDE.md §5, ajout
 * v0.13).
 */
class AdminStatisticsService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(?CarbonInterface $periodStart, ?CarbonInterface $periodEnd): array
    {
        return [
            'structures' => $this->structureCounts(),
            'geography' => $this->geography(),
            'activity' => $this->activity($periodStart, $periodEnd),
            'reviews' => $this->reviewStats(),
            'disputes' => $this->disputeStats(),
        ];
    }

    /**
     * Nombre total de structures par type (Garagiste/Market Space) et par
     * statut dérivé (approuvé/en attente/suspendu/rejeté) — lu depuis
     * `professional_registrations` (jamais depuis Garage/MarketSpaceAccount,
     * qui n'existent qu'une fois le dossier approuvé, CLAUDE.md §5, ajout
     * v0.6).
     *
     * @return array<string, array<string, int>>
     */
    private function structureCounts(): array
    {
        $counts = [
            AccountType::Garagiste->value => ['approved' => 0, 'pending' => 0, 'suspended' => 0, 'rejected' => 0, 'total' => 0],
            AccountType::MarketSpace->value => ['approved' => 0, 'pending' => 0, 'suspended' => 0, 'rejected' => 0, 'total' => 0],
        ];

        $registrations = DB::table('professional_registrations')
            ->join('users', 'users.id', '=', 'professional_registrations.user_id')
            ->select('users.role', 'professional_registrations.status', 'professional_registrations.suspended_at')
            ->get();

        foreach ($registrations as $registration) {
            if (! array_key_exists($registration->role, $counts)) {
                continue;
            }

            $status = match (true) {
                $registration->status === RegistrationStatus::Rejected->value => 'rejected',
                $registration->status === RegistrationStatus::Pending->value => 'pending',
                $registration->suspended_at !== null => 'suspended',
                default => 'approved',
            };

            $counts[$registration->role][$status]++;
            $counts[$registration->role]['total']++;
        }

        return $counts;
    }

    /**
     * Répartition géographique par ville et par région, sur les structures
     * approuvées (seules à porter ces champs — CLAUDE.md §5, ajout v0.17),
     * garages et Market Space confondus. Une entrée à `city`/`region` null
     * ("Non renseigné") regroupe les profils qui n'ont pas encore renseigné
     * le champ.
     *
     * @return array{by_city: array<int, array{city: ?string, city_label: string, count: int}>, by_region: array<int, array{region: ?string, region_label: string, count: int}>}
     */
    private function geography(): array
    {
        $cityCounts = $this->mergeCounts(
            Garage::query()->selectRaw('city, count(*) as aggregate')->groupBy('city')->pluck('aggregate', 'city'),
            MarketSpaceAccount::query()->selectRaw('city, count(*) as aggregate')->groupBy('city')->pluck('aggregate', 'city'),
        );

        $regionCounts = $this->mergeCounts(
            Garage::query()->selectRaw('region, count(*) as aggregate')->groupBy('region')->pluck('aggregate', 'region'),
            MarketSpaceAccount::query()->selectRaw('region, count(*) as aggregate')->groupBy('region')->pluck('aggregate', 'region'),
        );

        return [
            'by_city' => collect($cityCounts)->map(fn (int $count, string $city) => [
                'city' => $city !== '' ? $city : null,
                'city_label' => $city !== '' ? City::from($city)->label() : 'Non renseigné',
                'count' => $count,
            ])->values()->all(),
            'by_region' => collect($regionCounts)->map(fn (int $count, string $region) => [
                'region' => $region !== '' ? $region : null,
                'region_label' => $region !== '' ? Region::from($region)->label() : 'Non renseigné',
                'count' => $count,
            ])->values()->all(),
        ];
    }

    /**
     * Fusionne les comptages Garage/Market Space pour une même colonne
     * (city ou region) — une valeur absente (SQL NULL) revient comme clé ''
     * (PHP convertit toute clé de tableau `null` en chaîne vide).
     *
     * @param  Collection<string, int>  $garageCounts
     * @param  Collection<string, int>  $marketSpaceCounts
     * @return array<string, int>
     */
    private function mergeCounts(Collection $garageCounts, Collection $marketSpaceCounts): array
    {
        $merged = [];

        foreach ([$garageCounts, $marketSpaceCounts] as $counts) {
            foreach ($counts as $key => $count) {
                $merged[$key] = ($merged[$key] ?? 0) + (int) $count;
            }
        }

        return $merged;
    }

    /**
     * Volume d'activité créé/soldé sur la période demandée (bornes
     * optionnelles, totaux depuis le début si absentes) : RDV, devis émis
     * (statut au-delà de "draft"), commandes, factures générées (devis
     * facturés + commandes payées) et montant total facturé.
     *
     * @return array<string, mixed>
     */
    private function activity(?CarbonInterface $periodStart, ?CarbonInterface $periodEnd): array
    {
        $appointmentsCount = Appointment::query()
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('created_at', '<=', $periodEnd))
            ->count();

        $quotesIssuedCount = Quote::query()
            ->where('status', '!=', QuoteStatus::Draft->value)
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('created_at', '<=', $periodEnd))
            ->count();

        $ordersCount = Order::query()
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('created_at', '<=', $periodEnd))
            ->count();

        $invoicedQuotesCount = Quote::query()
            ->where('status', QuoteStatus::Invoiced->value)
            ->when($periodStart, fn ($q) => $q->where('paid_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('paid_at', '<=', $periodEnd))
            ->count();

        $paidOrdersCount = Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->when($periodStart, fn ($q) => $q->where('paid_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('paid_at', '<=', $periodEnd))
            ->count();

        $invoicedQuotesAmount = (float) QuoteLine::query()
            ->join('quote_versions', 'quote_versions.id', '=', 'quote_lines.quote_version_id')
            ->join('quotes', 'quotes.id', '=', 'quote_versions.quote_id')
            ->where('quote_versions.document_type', QuoteDocumentType::Invoice->value)
            ->where('quotes.status', QuoteStatus::Invoiced->value)
            ->when($periodStart, fn ($q) => $q->where('quotes.paid_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('quotes.paid_at', '<=', $periodEnd))
            ->sum('quote_lines.line_total');

        $paidOrdersAmount = (float) DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.status', OrderStatus::Paid->value)
            ->when($periodStart, fn ($q) => $q->where('orders.paid_at', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('orders.paid_at', '<=', $periodEnd))
            ->sum('order_lines.line_total');

        return [
            'period' => [
                'start' => $periodStart?->toDateString(),
                'end' => $periodEnd?->toDateString(),
            ],
            'appointments_count' => $appointmentsCount,
            'quotes_issued_count' => $quotesIssuedCount,
            'orders_count' => $ordersCount,
            'invoices_count' => $invoicedQuotesCount + $paidOrdersCount,
            'total_invoiced_amount' => number_format($invoicedQuotesAmount + $paidOrdersAmount, 2, '.', ''),
        ];
    }

    /**
     * Nombre d'avis et note moyenne globale de la plateforme — avis visibles
     * uniquement, même filtre que côté recherche (CLAUDE.md §5, ajout v0.10).
     *
     * @return array{count: int, average_rating: ?float}
     */
    private function reviewStats(): array
    {
        $count = Review::query()->visible()->count();
        $average = $count > 0 ? Review::query()->visible()->avg('rating') : null;

        return [
            'count' => $count,
            'average_rating' => $average !== null ? round((float) $average, 1) : null,
        ];
    }

    /**
     * @return array{total: int, by_status: array<string, int>}
     */
    private function disputeStats(): array
    {
        $counts = Dispute::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        foreach (DisputeStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }
}
