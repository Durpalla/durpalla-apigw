<?php

use App\Http\Controllers\Reseller\ResellerBookingController;
use App\Http\Controllers\Reseller\ResellerProfileController;
use App\Http\Controllers\Reseller\ResellerTripController;
use App\Http\Controllers\Reseller\ResellerWalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reseller Booking API (Passport client-credentials)
|--------------------------------------------------------------------------
|
| Machine-to-machine endpoints for API-partner (reseller) parties. Guarded by
| `client` (Passport client-credentials bearer token) + `resolve.api.partner`
| which binds the owning Party. Bookings are settled instantly from the
| reseller's prepaid wallet (net of commission).
|
*/

Route::prefix('reseller/v1')
    ->middleware(['client', 'resolve.api.partner'])
    ->group(function () {
        Route::get('profile', [ResellerProfileController::class, 'show']);

        Route::get('wallet', [ResellerWalletController::class, 'balance']);
        Route::get('wallet/transactions', [ResellerWalletController::class, 'transactions']);

        Route::get('trips', [ResellerTripController::class, 'index']);
        Route::get('trips/{trip}', [ResellerTripController::class, 'show']);

        Route::get('bookings', [ResellerBookingController::class, 'index']);
        Route::post('bookings', [ResellerBookingController::class, 'store']);
        Route::get('bookings/{booking}', [ResellerBookingController::class, 'show']);
        Route::post('bookings/{booking}/cancel', [ResellerBookingController::class, 'cancel']);
    });
