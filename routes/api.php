<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\RestaurantMenuController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\RestaurantOrderController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminSettingController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\AdminMonitoringController;
use App\Http\Controllers\Api\ComplaintController;


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
    
    // W7.01 — Customer rating & complaint
    Route::post('orders/{id}/rate', [RatingController::class, 'store']);
    Route::post('complaints', [ComplaintController::class, 'store']);
});
// ============================================
// RESTAURANT PORTAL (protected — role:restaurant + approved)
// ============================================
Route::middleware(['auth:sanctum', 'role:restaurant', 'approved'])->prefix('restaurant')->group(function () {
    // Menu
    Route::get('menu', [RestaurantMenuController::class, 'index']);
    Route::post('menu/items', [RestaurantMenuController::class, 'store']);
    Route::put('menu/items/{id}', [RestaurantMenuController::class, 'update']);
    Route::patch('menu/items/{id}/availability', [RestaurantMenuController::class, 'toggleAvailability']);
    Route::delete('menu/items/{id}', [RestaurantMenuController::class, 'destroy']);

    // Orders (W4.01)
    Route::get('orders', [RestaurantOrderController::class, 'index']);
    Route::get('orders/{id}', [RestaurantOrderController::class, 'show']);
    Route::post('orders/external', [RestaurantOrderController::class, 'storeExternal']); // W4.03
    Route::post('orders/{id}/accept', [RestaurantOrderController::class, 'accept']);
    Route::post('orders/{id}/reject', [RestaurantOrderController::class, 'reject']);
    Route::patch('orders/{id}/preparing', [RestaurantOrderController::class, 'markPreparing']);
    Route::patch('orders/{id}/ready', [RestaurantOrderController::class, 'markReady']);
});

// ============================================
// RIDER PORTAL (protected — role:rider + approved)
// ============================================
Route::middleware(['auth:sanctum', 'role:rider', 'approved'])->prefix('rider')->group(function () {
    Route::get('status', [RiderController::class, 'status']);
    Route::post('availability', [RiderController::class, 'setAvailability']);
    Route::patch('location', [RiderController::class, 'updateLocation']);
    Route::patch('orders/{id}/pickup', [RiderController::class, 'markPickedUp']);
    Route::patch('orders/{id}/delivered', [RiderController::class, 'markDelivered']);
    Route::post('offers/{id}/accept', [RiderController::class, 'acceptOffer']);
});

// ============================================
// ADMIN (protected — role:admin)
// ============================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('users/{id}/approve', [AdminUserController::class, 'approve']);
    Route::get('settings', [AdminSettingController::class, 'showSettings']);
    Route::patch('settings/fee', [AdminSettingController::class, 'updateFee']);
    Route::get('payments', [AdminPaymentController::class, 'index']);
    Route::get('payments/{id}', [AdminPaymentController::class, 'show']);
    Route::post('payments/{id}/remittance', [AdminPaymentController::class, 'recordRemittance']);
    Route::post('payments/{id}/payout', [AdminPaymentController::class, 'recordPayout']);
    Route::get('ratings', [AdminMonitoringController::class, 'ratings']);
    Route::get('attendance', [AdminMonitoringController::class, 'attendance']);
    Route::get('complaints', [AdminMonitoringController::class, 'complaints']);
    Route::post('complaints/{id}/resolve', [AdminMonitoringController::class, 'resolveComplaint']);
});