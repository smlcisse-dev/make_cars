<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\QuoteVersionResource;
use App\Models\Appointment;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quoteService) {}

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->user_id === $request->user()->id, 404);
    }

    private function authorizeQuote(Request $request, Quote $quote): void
    {
        abort_unless($quote->user_id === $request->user()->id, 404);
    }

    /**
     * Tous les devis du client, avec ou sans RDV associé (CLAUDE.md §5,
     * ajout v0.9).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $quotes = $request->user()->quotes()
            ->with(['garage', 'appointment', 'versions.lines'])
            ->latest()
            ->paginate();

        return QuoteResource::collection($quotes);
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        return $this->success(new QuoteResource($quote->load('versions.lines', 'garage', 'appointment')));
    }

    public function showForAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        $quote = $appointment->quote()->with('versions.lines')->firstOrFail();

        return $this->success(new QuoteResource($quote));
    }

    /**
     * Action digitale explicite, jamais déduite du chat (CLAUDE.md §5,
     * ajout v0.8) : rattachée précisément à la version concernée. Même
     * garde-fou que la décision par email pour un client "compte express"
     * (CLAUDE.md §5, ajout v0.9) — mutualisé via QuoteService.
     */
    public function accept(Request $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        $version = $this->quoteService->accept($quote, $version, $request->user());

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Devis accepté.');
    }

    public function reject(Request $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        $version = $this->quoteService->reject($quote, $version, $request->user());

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Devis refusé.');
    }

    public function downloadPdf(Request $request, Quote $quote, QuoteVersion $version): StreamedResponse
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($version->quote_id === $quote->id, 404);
        abort_if($version->pdf_path === null, 404, 'PDF pas encore généré pour cette version.');

        return Storage::disk($version->pdf_disk)->download($version->pdf_path);
    }
}
