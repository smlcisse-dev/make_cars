<?php

namespace App\Http\Middleware;

use App\Support\QueryCounter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journalise toute requête HTTP plus longue que
 * `logging.slow_requests.threshold_ms` : route, durée, nombre de requêtes
 * SQL et leur durée cumulée. Le temps SQL comparé à la durée totale dit
 * si la lenteur vient de la base (latence Supabase, trop de requêtes) ou du
 * code. Mesuré avant l'envoi de la réponse : le travail fait ensuite
 * (emails, DeferredMail) n'est pas compté, la personne ne l'attend pas.
 */
class LogSlowRequests
{
    public function __construct(private readonly QueryCounter $queries) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('logging.slow_requests.enabled')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $queriesBefore = $this->queries->count;
        $queryTimeBefore = $this->queries->totalMilliseconds;

        $response = $next($request);

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($durationMs >= (int) config('logging.slow_requests.threshold_ms')) {
            $route = $request->route();

            Log::warning('Requête lente', [
                'method' => $request->method(),
                'route' => $route ? '/'.ltrim($route->uri(), '/') : '/'.ltrim($request->path(), '/'),
                'route_name' => $route?->getName(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'sql_queries' => $this->queries->count - $queriesBefore,
                'sql_ms' => (int) round($this->queries->totalMilliseconds - $queryTimeBefore),
            ]);
        }

        return $response;
    }
}
