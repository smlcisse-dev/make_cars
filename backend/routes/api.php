<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::prefix('auth')->group(base_path('routes/api/auth.php'));
// Route::prefix('admin')->middleware(['auth:sanctum'])->group(base_path('routes/api/admin.php'));
// Route::prefix('garage')->middleware(['auth:sanctum'])->group(base_path('routes/api/garage.php'));
// Route::prefix('market-space')->middleware(['auth:sanctum'])->group(base_path('routes/api/market-space.php'));
// Route::prefix('mobile')->middleware(['auth:sanctum'])->group(base_path('routes/api/mobile.php'));
