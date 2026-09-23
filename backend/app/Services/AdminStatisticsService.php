<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\QuoteDocumentType;
use App\Enums\QuoteStatus;
use App\Enums\RegistrationStatus;
use App\Models\Appointment;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Review;
use Carbon\CarbonInterface;
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
     * statut dérivé (approuvé/en attente/profil à compléter/suspendu/rejeté)
     * — lu depuis `professional_registrations`, seule source du statut
     * (CLAUDE.md §5, ajouts v0.6 et v0.26).
     *
     * @return array<string, array<string, int>>
     */
    private function structureCounts(): array
    {
        $counts = [
            AccountType::Garagiste->value => ['approved' => 0, 'pending' => 0, 'profile_incomplete' => 0, 'suspended' => 0, 'rejected' => 0, 'total' => 0],
            AccountType::MarketSpace->value => ['approved' => 0, 'pending' => 0, 'profile_incomplete' => 0, 'suspended' => 0, 'rejected' => 0, 'total' => 0],
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
                $registration->status === RegistrationStatus::ProfileIncomplete->value => 'profile_incomplete',
                $registration->suspended_at !== null => 'suspended',
                default => 'approved',
            };

            $counts[$registration->role][$status]++;
            $counts[$registration->role]['total']++;
        }

        return $counts;
    }

    /**
     * Répartition géographique par département, séparément pour les garages
     * et pour les Market Space (CLAUDE.md §5, ajouts v0.17 et v0.19) —
     * uniquement les structures au dossier approuvé, suspendues comprises :
     * données destinées aux autorités, qui ne doivent compter que des
     * structures validées. Depuis v0.26 un profil existe dès la vérification
     * de l'email, d'où le filtre explicite sur le statut du dossier. Une entrée à
     * `department_id` null ("Non renseigné") regroupe les profils qui n'ont
     * pas encore renseigné leur localisation ; elle est toujours en dernier.
     *
     * @return array<string, array<int, array{department_id: ?int, department_name: string, count: int}>>
     */
    private function geography(): array
    {
        return [
            AccountType::Garagiste->value => $this->departmentBreakdown('garages'),
            AccountType::MarketSpace->value => $this->departmentBreakdown('market_space_accounts'),
        ];
    }

    /**
     * @return array<int, array{department_id: ?int, department_name: string, count: int}>
     */
    private function departmentBreakdown(string $profileTable): array
    {
        return DB::table($profileTable)
            ->join('professional_registrations', 'professional_registrations.user_id', '=', "{$profileTable}.user_id")
            ->where('professional_registrations.status', RegistrationStatus::Approved->value)
            ->leftJoin('departments', 'departments.id', '=', "{$profileTable}.department_id")
            ->selectRaw('departments.id as department_id, departments.name as department_name, count(*) as aggregate')
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(fn (object $row) => [
                'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
                'department_name' => $row->department_name ?? 'Non renseigné',
                'count' => (int) $row->aggregate,
            ])
            ->sortBy(fn (array $row) => [$row['department_id'] === null ? 1 : 0, $row['department_name']])
            ->values()
            ->all();
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
