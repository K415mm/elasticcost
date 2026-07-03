<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
|
| All API routes are versioned under /api/v1/.
| Authentication uses Laravel Passport (OAuth2).
| Role-based access control via 'role' middleware.
|
*/

Route::prefix('v1')->group(function () {

    // Public auth routes (no token required)
    Route::post('auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Authenticated routes
    Route::middleware('auth:api')->group(function () {

        // User profile & token management
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');

        // Client role — view own data
        Route::middleware('role:client')->group(function () {
            // Client-specific endpoints will be added here
        });

        // Manager role — manage clients, scenarios, assets
        Route::middleware('role:manager')->group(function () {
            // Manager-specific endpoints will be added here
        });

        // Sales Manager role — manage sales pipeline, partners
        Route::middleware('role:sales_manager')->group(function () {
            // Sales manager-specific endpoints will be added here
        });

        // Partner role — limited external access
        Route::middleware('role:partner')->group(function () {
            // Partner-specific endpoints will be added here
        });

        // CEO role — full access
        Route::middleware('role:ceo')->group(function () {
            // CEO-specific endpoints will be added here
        });

        // Multi-role routes (e.g., manager + ceo)
        Route::middleware('role:manager|ceo')->group(function () {
            // Shared management endpoints will be added here
        });
    });
});
