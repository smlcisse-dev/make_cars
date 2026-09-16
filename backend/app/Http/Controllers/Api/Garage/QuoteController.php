<?php

namespace App\Http\Controllers\Api\Garage;

use App\Enums\AppointmentStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Requests\Garage\QuoteLinesRequest;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\QuoteVersionResource;
use App\Models\Appointment;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly QuoteService $quoteService) {}

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->garage_id === $this->authenticatedGarage($request)->id, 404);
    }

    private function authorizeQuote(Request $request, Quote $quote): void
    {
        abort_unless($quote->appointment->garage_id === $this->authenticatedGarage($request)->id, 404);
    }

    /**
     * Un devis n'est créé qu'après un RDV confirmé (diagnostic effectué sur
     * place — CLAUDE.md §5, ajout v0.8), un seul par RDV.
     */
    public function store(QuoteLinesRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        abort_unless($appointment->status === AppointmentStatus::Confirmed, 403, 'Le RDV doit être confirmé avant de créer un devis.');
        abort_if($appointment->quote()->exists(), 403, 'Un devis existe déjà pour ce RDV.');

        $quote = $this->quoteService->createDraft($appointment, $request->array('lines'));

        return $this->success(new QuoteResource($quote->load('versions.lines')), 'Devis créé en brouillon.', 201);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        $quote = $appointment->quote()->with('versions.lines')->firstOrFail();

        return $this->success(new QuoteResource($quote));
    }

    public function updateVersion(QuoteLinesRequest $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($version->quote_id === $quote->id, 404);
        abort_unless($version->sent_at === null, 403, 'Cette version a déjà été envoyée.');

        $version = $this->quoteService->updateDraftLines($version, $request->array('lines'));

        return $this->success(new QuoteVersionResource($version), 'Brouillon mis à jour.');
    }

    public function send(Request $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($version->quote_id === $quote->id, 404);
        abort_unless($version->sent_at === null, 403, 'Cette version a déjà été envoyée.');

        $version = $this->quoteService->send($version);

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Devis envoyé au client.');
    }

    /**
     * Renégociation après un refus (CLAUDE.md §5, ajout v0.8) : nouvelle
     * version brouillon, à envoyer séparément via send().
     */
    public function storeNextVersion(QuoteLinesRequest $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === QuoteStatus::Rejected, 403, 'Le devis précédent n\'a pas été refusé.');

        $version = $this->quoteService->createNextVersion($quote, $request->array('lines'));

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Nouvelle version créée en brouillon.', 201);
    }

    public function start(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === QuoteStatus::Accepted, 403, 'Le devis n\'a pas été validé par le client.');

        $quote = $this->quoteService->start($quote);

        return $this->success(new QuoteResource($quote), 'Prestation démarrée.');
    }

    /**
     * Paiement manuel V1 (espèces ou autre, en attendant l'agrégateur en
     * ligne — CLAUDE.md §7) : déclenche la génération de la facture.
     */
    public function markPaid(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === QuoteStatus::InProgress, 403, 'La prestation n\'est pas en cours.');

        $quote = $this->quoteService->markPaid($quote);

        return $this->success(new QuoteResource($quote->load('versions.lines')), 'Paiement enregistré, facture générée.');
    }

    /**
     * Négociation infructueuse : clôture le RDV sans prestation ni facture
     * (CLAUDE.md §5, ajout v0.8).
     */
    public function abandon(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === QuoteStatus::Rejected, 403, 'Le devis n\'a pas été refusé.');

        $quote = $this->quoteService->abandon($quote);

        return $this->success(new QuoteResource($quote), 'RDV clôturé sans suite.');
    }

    public function downloadPdf(Request $request, Quote $quote, QuoteVersion $version): StreamedResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($version->quote_id === $quote->id, 404);
        abort_if($version->pdf_path === null, 404, 'PDF pas encore généré pour cette version.');

        return Storage::disk($version->pdf_disk)->download($version->pdf_path);
    }
}
