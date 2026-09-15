<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Base controller for every API endpoint (Admin, Garage, MarketSpace, Mobile, Auth).
 *
 * Controllers stay thin: validate via Form Requests, delegate business logic to a
 * class in App\Services, and use these helpers to shape the JSON response.
 */
abstract class Controller extends BaseController
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        return response()->json(array_filter([
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
        ], fn ($value) => $value !== null), $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $message,
            'errors' => $errors,
        ], fn ($value) => $value !== null), $status);
    }
}
