<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\v1\ApiBookingController;

// Main app routes (no prefix) – /api/v1/site/init, /api/v1/auth/*, /api/v1/cart/*, /api/v1/booking/*, /api/v1/my/*, etc.
include(__DIR__ . '/v1/customer.php');

// Customer API (Sanctum) – /api/v1/customer/auth/register, login, logout, me, booking/check
Route::prefix('customer')->group(function () {
    Route::post('auth/register', [CustomerAuthController::class, 'register']);
    Route::post('auth/login', [CustomerAuthController::class, 'login']);
    Route::middleware(['auth:customer'])->group(function () {
        Route::post('auth/logout', [CustomerAuthController::class, 'logout']);
        Route::get('auth/me', [CustomerAuthController::class, 'me']);
        Route::get('booking/check/{id}', [ApiBookingController::class, 'checkAsCustomer']);
    });
});

Route::group(['prefix' => 'customer'], function () {
    include(__DIR__ . '/v1/supervisor.php');
});

Route::group(['prefix' => 'merchant'], function () {
    include(__DIR__ . '/v1/merchant.php');
});
