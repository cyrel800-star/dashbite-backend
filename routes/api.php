<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::post('otp/request', [OtpController::class, 'request'])->middleware('throttle:otp');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);
        Route::get('me', [LoginController::class, 'me']);
    });
});

// Operational routes — PDF AC #1 (unapproved denied, availability unchanged)
Route::middleware(['auth:sanctum', 'role:rider', 'approved'])->group(function () {
    Route::post('/rider/availability', fn () => response()->json(['ok' => true]));
});

Route::middleware(['auth:sanctum', 'role:restaurant', 'approved'])->group(function () {
    Route::post('/restaurant/orders/{id}/accept', fn () => response()->json(['ok' => true]));
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/admin/users/{id}/approve', fn () => response()->json(['ok' => true]));
});