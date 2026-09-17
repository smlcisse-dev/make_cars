<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Admin\StatisticsRequest;
use App\Services\AdminStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Données agrégées pour appuyer les politiques de régulation/formalisation
 * du secteur auprès des autorités béninoises (CLAUDE.md §1, ajout v0.17).
 */
class StatisticsController extends Controller
{
    public function __construct(private readonly AdminStatisticsService $statisticsService) {}

    public function index(StatisticsRequest $request): JsonResponse
    {
        $start = $request->filled('start_date') ? CarbonImmutable::parse($request->string('start_date')->toString())->startOfDay() : null;
        $end = $request->filled('end_date') ? CarbonImmutable::parse($request->string('end_date')->toString())->endOfDay() : null;

        return $this->success($this->statisticsService->generate($start, $end));
    }
}
