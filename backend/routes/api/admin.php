<?php

use App\Http\Controllers\Api\Admin\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('registrations', [RegistrationController::class, 'index']);
    Route::get('registrations/{registration}', [RegistrationController::class, 'show']);
    Route::post('registrations/{registration}/approve', [RegistrationController::class, 'approve']);
    Route::post('registrations/{registration}/reject', [RegistrationController::class, 'reject']);
    Route::get('registrations/{registration}/documents/{document}', [RegistrationController::class, 'downloadDocument'])
        ->name('admin.registrations.documents.download');
});
