<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Broadcasting\BroadcastController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\v1\ApiBookingController;
use App\Http\Controllers\Api\v1\Merchant\MerchantAuthController;

// Pusher private/presence auth (Bearer) — Sanctum guards only (Passport `api` can 500 on Sanctum tokens)
Route::post('pusher/auth', [BroadcastController::class, 'authenticate'])
    ->middleware('auth:merchant_api,merchant_staff_api,customer,agent');

// Main app routes (no prefix) – /api/v1/site/init, /api/v1/auth/*, /api/v1/cart/*, /api/v1/booking/*, /api/v1/my/*, etc.
include(__DIR__ . '/v1/customer.php');

// Agent mobile app API – /api/v1/agent/*
include(__DIR__ . '/v1/agent.php');

// Merchant Desk Pro auth – /api/v1/auth/merchant/*
Route::prefix('auth')->group(function () {
    Route::post('merchant/login', [MerchantAuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('merchant/verify-2fa', [MerchantAuthController::class, 'verify2fa'])->middleware('throttle:auth');
    Route::post('merchant/forgot-password', [MerchantAuthController::class, 'forgotPassword'])->middleware('throttle:otp');
    Route::post('merchant/verify-reset-otp', [MerchantAuthController::class, 'verifyResetOtp'])->middleware('throttle:otp');
    Route::post('merchant/reset-password', [MerchantAuthController::class, 'resetPassword'])->middleware('throttle:auth');
});

// Customer API (Sanctum) – /api/v1/customer/auth/register, login, logout, me, booking/check
Route::prefix('customer')->group(function () {
    Route::post('auth/register', [CustomerAuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('auth/login', [CustomerAuthController::class, 'login'])->middleware('throttle:auth');
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
