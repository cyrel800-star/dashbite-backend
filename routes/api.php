<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\RestaurantMenuController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckoutController;

// ============================================
// AUTH (public)
// ============================================
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::post('otp/request', [OtpController::class, 'request'])->middleware('throttle:otp');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);
        Route::get('me', [LoginController::class, 'me']);
    });
});

// ============================================
// CATALOG (public — customer view)
// ============================================
Route::prefix('catalog')->group(function () {
    Route::get('restaurants', [CatalogController::class, 'restaurants']);
    Route::get('restaurants/{id}', [CatalogController::class, 'showRestaurant']);
    Route::get('restaurants/{id}/menu', [CatalogController::class, 'menu']);
});
// ============================================
// CHECKOUT (protected — role:customer + approved)
// ============================================
Route::middleware(['auth:sanctum', 'role:customer', 'approved'])->prefix('checkout')->group(function () {
    Route::post('/', [CheckoutController::class, 'store']);
    Route::get('orders/{id}', [CheckoutController::class, 'show']);
});
// ============================================
// RESTAURANT PORTAL (protected — role:restaurant + approved)
// ============================================
Route::middleware(['auth:sanctum', 'role:restaurant', 'approved'])->prefix('restaurant')->group(function () {
    // Menu management
    Route::get('menu', [RestaurantMenuController::class, 'index']);
    Route::post('menu/items', [RestaurantMenuController::class, 'store']);
    Route::put('menu/items/{id}', [RestaurantMenuController::class, 'update']);
    Route::patch('menu/items/{id}/availability', [RestaurantMenuController::class, 'toggleAvailability']);
    Route::delete('menu/items/{id}', [RestaurantMenuController::class, 'destroy']);

    // Orders (stub — W4.01 will implement)
    Route::post('orders/{id}/accept', fn () => response()->json(['ok' => true]));
});

// ============================================
// RIDER PORTAL (protected — role:rider + approved)
// ============================================
Route::middleware(['auth:sanctum', 'role:rider', 'approved'])->prefix('rider')->group(function () {
    Route::post('availability', fn () => response()->json(['ok' => true]));
});

// ============================================
// ADMIN (protected — role:admin)
// ============================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('users/{id}/approve', fn () => response()->json(['ok' => true]));
});