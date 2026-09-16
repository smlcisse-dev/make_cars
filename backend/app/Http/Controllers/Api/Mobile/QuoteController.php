<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Api\Controller;
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
    public function __construct(private readonly QuoteService $quoteService) {}

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->user_id === $request->user()->id, 404);
    }

    private function authorizeQuote(Request $request, Quote $quote): void
    {
        abort_unless($quote->appointment->user_id === $request->user()->id, 404);
    }

    /**
     * La version courante n'est acceptable/refusable que si elle a bien été
     * envoyée par le garagiste (jamais un brouillon).
     */
    private function authorizeCurrentSentVersion(Quote $quote, QuoteVersion $version): void
    {
        abort_unless($version->quote_id === $quote->id, 404);
        $current = $quote->currentVersion()->first();
        abort_unless($current && $current->id === $version->id, 403, 'Seule la version la plus récente du devis peut être traitée.');
        abort_unless($version->sent_at !== null, 403, 'Ce devis n\'a pas encore été envoyé.');
        abort_unless(in_array($quote->status, [QuoteStatus::Sent, QuoteStatus::Negotiating], strict: true), 403, 'Ce devis n\'est plus en attente d\'une décision.');
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        $quote = $appointment->quote()->with('versions.lines')->firstOrFail();

        return $this->success(new QuoteResource($quote));
    }

    /**
     * Action digitale explicite, jamais déduite du chat (CLAUDE.md §5,
     * ajout v0.8) : rattachée précisément à la version concernée.
     */
    public function accept(Request $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        $this->authorizeCurrentSentVersion($quote, $version);

        $version = $this->quoteService->accept($version, $request->user());

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Devis accepté.');
    }

    public function reject(Request $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
        $this->authorizeCurrentSentVersion($quote, $version);

        $version = $this->quoteService->reject($version, $request->user());

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
