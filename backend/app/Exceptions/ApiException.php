<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Erreur métier renvoyée telle quelle au client, avec un `code` stable que
 * le frontend peut tester (ex. `verification_expired`) plutôt que de devoir
 * interpréter le message — même forme que la réponse `profile_incomplete`
 * de EnsureProfileIsComplete : `{ message, code, ...extra }`.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra  champs supplémentaires ajoutés à la réponse
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            ...$this->extra,
        ], $this->status);
    }
}
