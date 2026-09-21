<?php

namespace App\Http\Controllers\Api\Garage;

use App\Enums\AccountType;
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
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
        abort_unless($quote->garage_id === $this->authenticatedGarage($request)->id, 404);
    }

    /**
     * Relations lues par QuoteResource (`client` via `user`, `versions`) :
     * plusieurs actions du service renvoient un modèle rafraîchi (`fresh()`)
     * ou nu, sans relations — même correctif centralisé que
     * AppointmentController::loadAppointmentRelations().
     */
    private function loadQuoteRelations(Quote $quote): Quote
    {
        return $quote->load(['versions.lines', 'user']);
    }

    /**
     * Création directe, sans RDV (CLAUDE.md §5, ajout v0.9) : un devis est
     * nécessaire dès qu'il y a une prestation, que le client se soit présenté
     * avec ou sans RDV préalable. `client_id` peut être un compte
     * automobiliste existant ou un compte "express" créé via
     * ClientController::storeExpress au préalable.
     */
    public function store(QuoteLinesRequest $request): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($request->filled('client_id'), 422, 'Le client est obligatoire pour créer un devis sans RDV.');

        $client = User::where('id', $request->integer('client_id'))
            ->where('role', AccountType::Automobiliste)
            ->firstOrFail();

        $quote = $this->quoteService->createDraft($garage, $client, null, $request->array('lines'));

        return $this->success(new QuoteResource($this->loadQuoteRelations($quote)), 'Devis créé en brouillon.', 201);
    }

    /**
     * Création depuis un RDV existant, conservé pour la traçabilité
     * (CLAUDE.md §5, ajout v0.9) : le RDV n'est plus une condition
     * obligatoire pour créer un devis, mais reste le chemin naturel quand le
     * client est passé par la prise de RDV. Toujours un seul devis par RDV.
     */
    public function storeForAppointment(QuoteLinesRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        abort_unless($appointment->status === AppointmentStatus::Confirmed, 403, 'Le RDV doit être confirmé avant de créer un devis.');
        abort_if($appointment->quote()->exists(), 403, 'Un devis existe déjà pour ce RDV.');

        $quote = $this->quoteService->createDraft($appointment->garage, $appointment->user, $appointment, $request->array('lines'));

        return $this->success(new QuoteResource($this->loadQuoteRelations($quote)), 'Devis créé en brouillon.', 201);
    }

    /**
     * Liste de tous les devis du garage, avec ou sans RDV associé
     * (CLAUDE.md §5, ajout v0.9).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $quotes = $this->authenticatedGarage($request)->quotes()
            ->with(['user', 'appointment', 'versions.lines'])
            ->latest()
            ->paginate();

        return QuoteResource::collection($quotes);
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        return $this->success(new QuoteResource($this->loadQuoteRelations($quote)));
    }

    public function showForAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        $quote = $this->loadQuoteRelations($appointment->quote()->firstOrFail());

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

        $quote = $this->loadQuoteRelations($this->quoteService->start($quote));

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

        $quote = $this->loadQuoteRelations($this->quoteService->markPaid($quote));

        return $this->success(new QuoteResource($quote), 'Paiement enregistré, facture générée.');
    }

    /**
     * Négociation infructueuse : clôture le RDV sans prestation ni facture
     * (CLAUDE.md §5, ajout v0.8).
     */
    public function abandon(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === QuoteStatus::Rejected, 403, 'Le devis n\'a pas été refusé.');

        $quote = $this->loadQuoteRelations($this->quoteService->abandon($quote));

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
