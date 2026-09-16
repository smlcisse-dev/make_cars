<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\QuoteVersionResource;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;

/**
 * Décision d'un devis via le lien signé reçu par email par un client
 * "compte express" sans app (CLAUDE.md §5, ajout v0.9). Routes protégées par
 * le middleware `signed` (pas de Sanctum) : la signature + expiration
 * remplacent l'authentification, la décision reste tracée sur le compte du
 * client (`decided_by`) exactement comme depuis l'app. Réponse JSON brute
 * (API REST pure — CLAUDE.md §4) : le rendu d'une page de confirmation est
 * laissé au futur frontend Vue, qui pourra appeler ces mêmes endpoints.
 */
class QuoteEmailDecisionController extends Controller
{
    public function __construct(private readonly QuoteService $quoteService) {}

    public function accept(Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->quoteService->assertVersionIsDecidable($quote, $version);

        $version = $this->quoteService->accept($version, $quote->user);

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Devis accepté.');
    }

    public function reject(Quote $quote, QuoteVersion $version): JsonResponse
    {
        $this->quoteService->assertVersionIsDecidable($quote, $version);

        $version = $this->quoteService->reject($version, $quote->user);

        return $this->success(new QuoteVersionResource($version->load('lines')), 'Devis refusé.');
    }
}
