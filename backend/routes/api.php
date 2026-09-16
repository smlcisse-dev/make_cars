<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::prefix('auth')->group(base_path('routes/api/auth.php'));
Route::prefix('admin')->group(base_path('routes/api/admin.php'));
Route::prefix('garage')->group(base_path('routes/api/garage.php'));
Route::prefix('mobile')->group(base_path('routes/api/mobile.php'));
Route::prefix('market-space')->group(base_path('routes/api/market-space.php'));
