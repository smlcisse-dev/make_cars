<?php

namespace App\Support;

/**
 * Nombre et durée cumulée des requêtes SQL exécutées, alimentés par un
 * écouteur DB::listen (AppServiceProvider) quand la journalisation des
 * requêtes lentes est active. Lu par le middleware LogSlowRequests.
 */
class QueryCounter
{
    public int $count = 0;

    public float $totalMilliseconds = 0.0;

    public function record(float $milliseconds): void
    {
        $this->count++;
        $this->totalMilliseconds += $milliseconds;
    }
}
