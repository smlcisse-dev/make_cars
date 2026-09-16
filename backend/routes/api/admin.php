<?php

use App\Http\Controllers\Api\Admin\GarageController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('registrations', [RegistrationController::class, 'index']);
    Route::get('registrations/{registration}', [RegistrationController::class, 'show']);
    Route::post('registrations/{registration}/approve', [RegistrationController::class, 'approve']);
    Route::post('registrations/{registration}/reject', [RegistrationController::class, 'reject']);
    Route::get('registrations/{registration}/documents/{document}', [RegistrationController::class, 'downloadDocument'])
        ->name('admin.registrations.documents.download');

    Route::get('garages', [GarageController::class, 'index']);
    Route::get('garages/{garage}', [GarageController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('products/{product}/approve', [ProductController::class, 'approve']);
    Route::post('products/{product}/reject', [ProductController::class, 'reject']);
});
