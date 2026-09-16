<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\QuoteDocumentType;
use App\Enums\QuoteLineType;
use App\Enums\QuoteStatus;
use App\Enums\QuoteVersionDecision;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deuxième maillon de la chaîne RDV → devis → validation → prestation →
 * paiement → facture (CLAUDE.md §5 règle 10, ajout v0.8). Les garde-fous
 * d'état (quel statut autorise quelle action) vivent dans les contrôleurs,
 * pas ici — cette classe applique les transitions, elle ne les autorise pas
 * (même répartition que les modules précédents).
 */
class QuoteService
{
    public function __construct(
        private readonly QuotePdfService $pdfService,
        private readonly ChatService $chatService,
        private readonly ProductService $productService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $linesData
     */
    public function createDraft(Appointment $appointment, array $linesData): Quote
    {
        return DB::transaction(function () use ($appointment, $linesData) {
            $quote = Quote::create([
                'appointment_id' => $appointment->id,
                'status' => QuoteStatus::Draft,
            ]);

            $this->createVersion($quote, 1, $linesData);

            return $quote;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesData
     */
    public function updateDraftLines(QuoteVersion $version, array $linesData): QuoteVersion
    {
        DB::transaction(function () use ($version, $linesData) {
            $version->lines()->delete();
            $this->attachLines($version, $linesData);
        });

        return $version->fresh('lines');
    }

    /**
     * Génère le PDF, envoie la version courante et notifie via le chat
     * (CLAUDE.md §5, ajout v0.8) — jamais de validation via le chat, il ne
     * sert qu'à transmettre le document.
     */
    public function send(QuoteVersion $version): QuoteVersion
    {
        $this->pdfService->generate($version);
        $version->update(['sent_at' => now()]);

        $quote = $version->quote;
        $quote->update(['status' => $version->version === 1 ? QuoteStatus::Sent : QuoteStatus::Negotiating]);

        $appointment = $quote->appointment;
        $this->chatService->postQuoteVersionMessage(
            $appointment->garage,
            $appointment->user,
            $version,
            $version->version === 1
                ? 'Nouveau devis envoyé pour votre rendez-vous.'
                : "Nouvelle proposition de devis (version {$version->version}) suite à votre refus."
        );

        return $version->fresh();
    }

    /**
     * Renégociation après un refus : nouvelle version brouillon, à envoyer
     * séparément via send() (CLAUDE.md §5, ajout v0.8).
     *
     * @param  array<int, array<string, mixed>>  $linesData
     */
    public function createNextVersion(Quote $quote, array $linesData): QuoteVersion
    {
        $nextNumber = $quote->versions()->max('version') + 1;

        return DB::transaction(fn () => $this->createVersion($quote, $nextNumber, $linesData));
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesData
     */
    private function createVersion(Quote $quote, int $number, array $linesData): QuoteVersion
    {
        $version = $quote->versions()->create([
            'version' => $number,
            'document_type' => QuoteDocumentType::Quote,
        ]);

        $this->attachLines($version, $linesData);

        return $version;
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesData
     */
    private function attachLines(QuoteVersion $version, array $linesData): void
    {
        $garage = $version->quote->appointment->garage;

        foreach ($linesData as $lineData) {
            $version->lines()->create($this->resolveLine($garage, $lineData));
        }
    }

    /**
     * @param  array<string, mixed>  $lineData
     * @return array<string, mixed>
     */
    private function resolveLine(Garage $garage, array $lineData): array
    {
        $type = QuoteLineType::from($lineData['type']);
        $quantity = (int) ($lineData['quantity'] ?? 1);

        if ($type === QuoteLineType::DiagnosisFee) {
            return [
                'type' => $type,
                'label' => $lineData['label'] ?? 'Frais de diagnostic',
                'unit_price' => $lineData['unit_price'],
                'quantity' => $quantity,
                'line_total' => $lineData['unit_price'] * $quantity,
            ];
        }

        if ($type === QuoteLineType::Service) {
            $service = RepairService::where('id', $lineData['repair_service_id'])
                ->where('garage_id', $garage->id)
                ->firstOrFail();

            return [
                'type' => $type,
                'repair_service_id' => $service->id,
                'label' => $service->name,
                'unit_price' => $service->price,
                'quantity' => $quantity,
                'line_total' => $service->price * $quantity,
            ];
        }

        /** @var Product $product */
        $product = Product::where('id', $lineData['product_id'])
            ->where('sellable_type', Garage::class)
            ->where('sellable_id', $garage->id)
            ->firstOrFail();

        abort_if($product->stock_quantity < $quantity, 422, 'Stock insuffisant pour ce produit.');

        return [
            'type' => $type,
            'product_id' => $product->id,
            'label' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'line_total' => $product->price * $quantity,
        ];
    }

    /**
     * Décision explicite du client, tracée et authentifiée — jamais déduite
     * du chat (CLAUDE.md §5, ajout v0.8). Le stock des lignes de pièces n'est
     * décrémenté qu'ici, à l'acceptation (CLAUDE.md §5, ajout v0.4).
     */
    public function accept(QuoteVersion $version, User $client): QuoteVersion
    {
        return DB::transaction(function () use ($version, $client) {
            $version->update([
                'decision' => QuoteVersionDecision::Accepted,
                'decided_at' => now(),
                'decided_by' => $client->id,
            ]);

            $version->quote->update(['status' => QuoteStatus::Accepted]);

            foreach ($version->lines()->where('type', QuoteLineType::Product)->get() as $line) {
                if ($line->product) {
                    $this->productService->decrementStock($line->product, $line->quantity);
                }
            }

            return $version->fresh();
        });
    }

    public function reject(QuoteVersion $version, User $client): QuoteVersion
    {
        $version->update([
            'decision' => QuoteVersionDecision::Rejected,
            'decided_at' => now(),
            'decided_by' => $client->id,
        ]);

        $version->quote->update(['status' => QuoteStatus::Rejected]);

        return $version->fresh();
    }

    public function start(Quote $quote): Quote
    {
        $quote->update(['status' => QuoteStatus::InProgress]);

        return $quote;
    }

    /**
     * Paiement manuel (V1 — pas encore d'agrégateur en ligne, CLAUDE.md §7) :
     * le devis devient facture, même contenu, nouveau PDF avec la mention
     * "Facture" (CLAUDE.md §5, ajout v0.8). Clôture le RDV avec prestation
     * réalisée (Appointment::wasCompletedWithService() = true).
     */
    public function markPaid(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $acceptedVersion = $quote->acceptedVersion()->with('lines')->firstOrFail();
            $nextNumber = $quote->versions()->max('version') + 1;

            $invoiceVersion = $quote->versions()->create([
                'version' => $nextNumber,
                'document_type' => QuoteDocumentType::Invoice,
            ]);

            foreach ($acceptedVersion->lines as $line) {
                $invoiceVersion->lines()->create([
                    'type' => $line->type,
                    'repair_service_id' => $line->repair_service_id,
                    'product_id' => $line->product_id,
                    'label' => $line->label,
                    'unit_price' => $line->unit_price,
                    'quantity' => $line->quantity,
                    'line_total' => $line->line_total,
                ]);
            }

            $quote->update(['status' => QuoteStatus::Invoiced, 'paid_at' => now()]);
            $quote->appointment->update(['status' => AppointmentStatus::Completed]);

            $this->pdfService->generate($invoiceVersion);
            $invoiceVersion->update(['sent_at' => now()]);

            $appointment = $quote->appointment;
            $this->chatService->postQuoteVersionMessage(
                $appointment->garage,
                $appointment->user,
                $invoiceVersion,
                'Facture disponible pour votre rendez-vous.'
            );

            return $quote->fresh();
        });
    }

    /**
     * Négociation infructueuse : le garagiste clôture le RDV sans prestation
     * ni facture (CLAUDE.md §5, ajout v0.8) — distinct d'un RDV terminé avec
     * prestation réalisée (cf. Appointment::wasCompletedWithService()).
     */
    public function abandon(Quote $quote): Quote
    {
        $quote->update(['status' => QuoteStatus::Abandoned]);
        $quote->appointment->update(['status' => AppointmentStatus::Completed]);

        return $quote->fresh();
    }
}
