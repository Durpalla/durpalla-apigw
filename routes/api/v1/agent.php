<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\Agent\AgentAuthController;
use App\Http\Controllers\Api\v1\Agent\AgentBookingController;
use App\Http\Controllers\Api\v1\Agent\AgentCommissionController;
use App\Http\Controllers\Api\v1\Agent\AgentDashboardController;
use App\Http\Controllers\Api\v1\Agent\AgentFundTopupController;
use App\Http\Controllers\Api\v1\Agent\AgentPaymentController;
use App\Http\Controllers\Api\v1\Agent\AgentProfileController;
use App\Http\Controllers\Api\v1\Agent\AgentReferredMerchantController;
use App\Http\Controllers\Api\v1\Agent\AgentReferredPropertyController;
use App\Http\Controllers\Api\v1\Agent\AgentTransportBookingController;
use App\Http\Controllers\Api\v1\Agent\AgentUpcomingTripController;
use App\Http\Controllers\Api\v1\Agent\AgentHotelController;
use App\Http\Controllers\Api\v1\Agent\AgentWalletController;
use App\Http\Controllers\Api\v1\Agent\AgentWithdrawalController;
use App\Http\Controllers\Api\v1\Agent\AgentWithdrawalMethodController;

// Agent mobile app API — all routes under /api/v1/agent/*
Route::prefix('agent')->middleware(['JsonResponse'])->group(function () {
    Route::post('auth/login', [AgentAuthController::class, 'login']);
    Route::post('auth/onboard', [AgentAuthController::class, 'onboard']);

    Route::middleware(['auth:agent', 'api.agent'])->group(function () {
        Route::get('my/profile', [AgentProfileController::class, 'show']);
        Route::post('my/profile/update', [AgentProfileController::class, 'update']);
        Route::post('my/profile/change-password', [AgentProfileController::class, 'changePassword']);
        Route::post('my/profile/fcm-token', [AgentProfileController::class, 'updateFcmToken']);
        Route::get('my/dashboard', [AgentDashboardController::class, 'show']);
        Route::get('my/bookings', [AgentBookingController::class, 'index']);
        Route::get('my/bookings/{id}', [AgentBookingController::class, 'show'])->whereNumber('id');
        Route::post('my/bookings/{id}/cancel', [AgentBookingController::class, 'cancel'])->whereNumber('id');
        Route::get('my/wallet', [AgentWalletController::class, 'show']);
        Route::get('my/wallet/statements', [AgentWalletController::class, 'statements']);
        Route::get('my/fund/topup-options', [AgentFundTopupController::class, 'options']);
        Route::get('my/fund/topups', [AgentFundTopupController::class, 'index']);
        Route::get('my/commission/history', [AgentCommissionController::class, 'index']);
        Route::get('my/withdrawals', [AgentWithdrawalController::class, 'index']);
        Route::get('my/withdrawal-init', [AgentWithdrawalController::class, 'init']);
        Route::post('my/withdrawal-request', [AgentWithdrawalController::class, 'store']);
        Route::get('my/withdrawal-method-list', [AgentWithdrawalMethodController::class, 'index']);
        Route::post('my/withdrawal-method-add', [AgentWithdrawalMethodController::class, 'store']);
        Route::get('my/referred-properties', [AgentReferredPropertyController::class, 'index']);
        Route::get('my/referred-merchants', [AgentReferredMerchantController::class, 'index']);
        Route::get('my/referred-merchants/{id}', [AgentReferredMerchantController::class, 'show']);

        // Browse transport / hotels (booking writes require agent.active below).
        Route::get('transport/search', [AgentTransportBookingController::class, 'search']);
        Route::get('transport/trip/{id}', [AgentTransportBookingController::class, 'trip'])->whereNumber('id');
        Route::get('transport/suggest', [AgentTransportBookingController::class, 'suggest']);
        Route::get('transport/suggest/{term}/{accept?}', [AgentTransportBookingController::class, 'suggest']);
        Route::get('transport/payment-methods', [AgentTransportBookingController::class, 'paymentMethods']);
        Route::get('transport/upcoming', [AgentUpcomingTripController::class, 'index']);
        Route::get('favourite-vehicles', [AgentUpcomingTripController::class, 'favourites']);

        Route::get('hotels/search', [AgentHotelController::class, 'index']);
        Route::get('hotels/city-suggest', [AgentHotelController::class, 'citySuggest']);
        Route::get('hotels/payment-methods', [AgentHotelController::class, 'paymentMethods']);
        Route::get('hotels/{id}/rooms', [AgentHotelController::class, 'rooms'])->whereNumber('id');
        Route::get('hotels/{id}', [AgentHotelController::class, 'show'])->whereNumber('id');
        Route::get('favourite-hotels', [AgentHotelController::class, 'favourites']);

        // Booking + referral mutations — approved (active) agents only.
        Route::middleware(['agent.active'])->group(function () {
            Route::post('my/referred-properties', [AgentReferredPropertyController::class, 'store']);
            Route::post('my/referred-merchants', [AgentReferredMerchantController::class, 'store']);
            Route::post('my/referred-merchants/{id}', [AgentReferredMerchantController::class, 'update']);
            Route::post('my/referred-merchants/{id}/submit', [AgentReferredMerchantController::class, 'submit']);
            Route::post('my/referred-merchants/{id}/documents', [AgentReferredMerchantController::class, 'uploadDocument']);

            Route::post('transport/lock', [AgentTransportBookingController::class, 'lock']);
            Route::post('transport/unlock', [AgentTransportBookingController::class, 'unlock']);
            Route::post('transport/confirm', [AgentTransportBookingController::class, 'confirm']);
            Route::post('payment/make', [AgentPaymentController::class, 'make']);
            Route::get('payment/status', [AgentPaymentController::class, 'status']);
            Route::post('my/fund/topup/gateway-init', [AgentFundTopupController::class, 'gatewayInit']);
            Route::post('my/fund/topup/bank-transfer', [AgentFundTopupController::class, 'bankTransfer']);
            Route::get('my/fund/topup/status', [AgentFundTopupController::class, 'status']);
            Route::post('favourite-vehicles', [AgentUpcomingTripController::class, 'addFavourite']);
            Route::delete('favourite-vehicles/{vehicleId}', [AgentUpcomingTripController::class, 'removeFavourite']);

            Route::post('hotels/hold', [AgentHotelController::class, 'hold']);
            Route::post('hotels/confirm', [AgentHotelController::class, 'confirm']);
            Route::post('favourite-hotels', [AgentHotelController::class, 'addFavourite']);
            Route::delete('favourite-hotels/{hotelId}', [AgentHotelController::class, 'removeFavourite']);
        });
    });
});
