<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Public\QuoteEmailDecisionRequest;
use App\Http\Resources\QuoteVersionResource;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Services\QuoteEmailDecisionService;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Décision d'un devis via le lien signé reçu par email par un client
 * "compte express" sans app (CLAUDE.md §5, ajouts v0.9 et v0.30). Une seule
 * URL signée (middleware `signed:relative`, pas de Sanctum) pour deux
 * usages : GET lit le devis sans aucun effet (un robot qui ouvre le lien
 * ne décide rien), POST `{ decision }` décide — action explicite du client,
 * tracée sur son compte (`decided_by`) exactement comme depuis l'app.
 */
class QuoteEmailDecisionController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService,
        private readonly QuoteEmailDecisionService $emailDecisionService,
    ) {}

    public function show(Request $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        abort_unless($version->quote_id === $quote->id, 404);

        return $this->success($this->emailDecisionService->show(
            $quote,
            $version,
            $this->quoteService->isVersionDecidable($quote, $version),
            $request->integer('expires') ?: null,
        ));
    }

    public function decide(QuoteEmailDecisionRequest $request, Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->quoteService->assertVersionIsDecidable($quote, $version);

        if ($request->input('decision') === 'accept') {
            $version = $this->quoteService->accept($version, $quote->user);
            $message = 'Devis accepté.';
        } else {
            $version = $this->quoteService->reject($version, $quote->user);
            $message = 'Devis refusé.';
        }

        return $this->success(new QuoteVersionResource($version->load('lines')), $message);
    }
}
