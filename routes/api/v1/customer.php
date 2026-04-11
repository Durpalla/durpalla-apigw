<?php

use App\Http\Controllers\Api\TransportApiController;
use App\Http\Controllers\Api\v1\ApiAgentCommissionController;
use App\Http\Controllers\Api\v1\ApiBookingController;
use App\Http\Controllers\Api\v1\ApiCancellationController;
use App\Http\Controllers\Api\v1\ApiCartController;
use App\Http\Controllers\Api\v1\ApiOrderController;
use App\Http\Controllers\Api\v1\ApiPaymentController;
use App\Http\Controllers\Api\v1\ApiSupportController;
use App\Http\Controllers\Api\v1\ApiWithdrawalController;
use App\Http\Controllers\Api\v1\ApiWithdrawalMethodController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\FaqController;
use App\Http\Controllers\Api\v1\FrontApiController;
use App\Http\Controllers\Api\v1\GatewayController;
use App\Http\Controllers\Api\v1\MyApiController;
use App\Http\Controllers\Api\v1\NidVerificationController;
use App\Http\Controllers\Api\v1\PageController;
use App\Http\Controllers\Api\v1\QuickBookController;
use App\Http\Controllers\Api\v1\SupervisorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['JsonResponse'])->group(function () {
    Route::get('offers', [FrontApiController::class, 'offers']);
    Route::get('site/init', [FrontApiController::class, 'init']);
    Route::get('mobile/init', [FrontApiController::class, 'mobileInit']);
    Route::get('page/{slug}', [FrontApiController::class, 'page']);
    Route::get('vehicles', [FrontApiController::class, 'vehicles']);
    Route::post('download/link', [FrontApiController::class, 'downloadLink']);

    Route::prefix('page')->group(function () {
        Route::get('{slug}', [PageController::class, 'show']);
    });

    Route::get('faq', [FaqController::class, 'index']);

    Route::prefix('support')->group(function () {
        Route::post('/send', [ApiSupportController::class, 'store']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('check', [AuthController::class, 'check']);
        Route::post('verify', [AuthController::class, 'verify']);
        Route::post('forgot', [AuthController::class, 'forgot']);
        Route::post('reset', [AuthController::class, 'reset']);
        Route::post('otp/resend', [AuthController::class, 'resendCode']);
    });

    Route::group(['prefix' => 'auth', 'middleware' => 'auth:api'], function() {
        Route::post('push/bind', [AuthController::class, 'bindPush']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    Route::get('/search', [FrontApiController::class, 'search']);
    Route::get('/available', [FrontApiController::class, 'search']);
    Route::get('/suggest/{term?}/{term2?}', [FrontApiController::class, 'suggest']);
    Route::get('/trip/{id}', [FrontApiController::class, 'trip']);

    // Transport API (same contract as durpalla: search, lock, unlock)
    Route::prefix('transport')->group(function () {
        Route::get('/search', [TransportApiController::class, 'search']);
        Route::get('/available', [TransportApiController::class, 'search']);
        Route::post('/lock', [TransportApiController::class, 'lock']);
        Route::post('/unlock', [TransportApiController::class, 'unlock']);
    });

    Route::prefix('cart')->group(function () {
        Route::post('/add', [TransportApiController::class, 'lock']);
        Route::post('/lock', [TransportApiController::class, 'lock']);
        Route::post('/remove', [ApiCartController::class, 'remove']);
        Route::post('/unlock', [ApiCartController::class, 'remove']);
        Route::get('/reset', [ApiCartController::class, 'resetLockdItems']);
    });

    /*************** AUTHENTICATED API ****************/

    Route::middleware(['auth:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('coupon/validate', [ApiOrderController::class, 'couponValidate']);

        Route::prefix('order')->group(function () {
            Route::post('/confirm', [TransportApiController::class, 'confirm']);
            Route::post('/transaction', [ApiOrderController::class, 'payment']);
        });

        Route::prefix('transport')->middleware(['auth:api'])->group(function () {
            Route::post('/booking/confirm', [TransportApiController::class, 'confirm']);
        });

        Route::prefix('booking')->group(function () {
            Route::post('confirm', [ApiOrderController::class, 'confirm']);
            Route::post('payment', [ApiOrderController::class, 'payment']);
            Route::get('check/{id}', [ApiBookingController::class, 'check']);
            Route::post('cancel', [ApiCancellationController::class, 'store']);
        });

        Route::prefix('payment')->group(function () {
            Route::post('make', [ApiPaymentController::class, 'make']);
            Route::post('validate', [ApiPaymentController::class, 'validateOrder']);
            Route::get('verify', [ApiPaymentController::class, 'verify']);
        });

        Route::prefix('gateway')->group(function () {
            Route::get('/', [GatewayController::class, 'index']);
        });

        Route::prefix('my')->group(function () {
            Route::post('/get-nid-number', [NidVerificationController::class, 'getNID']);
            Route::get('/withdrawals', [ApiWithdrawalController::class, 'index']);
            Route::get('/withdrawal-init', [ApiWithdrawalController::class, 'create']);
            Route::post('/withdrawal-request', [ApiWithdrawalController::class, 'store']);
            Route::get('/withdrawal-method-list', [ApiWithdrawalMethodController::class, 'index']);
            Route::post('/withdrawal-method-add', [ApiWithdrawalMethodController::class, 'store']);
            Route::post('nid-verification', [NidVerificationController::class, 'store']);
            Route::get('/wallet', [MyApiController::class, 'wallet']);
            Route::get('/commission/history', [ApiAgentCommissionController::class, 'index']);
            Route::post('/device-id', [MyApiController::class, 'updateDeviceId']);
            Route::get('/profile', [MyApiController::class, 'profile']);
            Route::post('profile/update', [MyApiController::class, 'update']);
            Route::put('update-profile', [MyApiController::class, 'updateProfile']);
            Route::post('/email/change', [MyApiController::class, 'changeEmail']);
            Route::post('/mobile/change', [MyApiController::class, 'changeMobile']);
            Route::post('/password/change', [MyApiController::class, 'changePassword']);
            Route::post('/profile/upload', [MyApiController::class, 'upload']);
            Route::post('/profile/upload/procedural', [MyApiController::class, 'uploadProcedural']);
            Route::get('/bookings', [MyApiController::class, 'bookings']);
            Route::get('/get-bookings', [MyApiController::class, 'getBookings']);
            Route::get('/cancellations', [MyApiController::class, 'cancellations']);
            Route::get('/activities', [MyApiController::class, 'activities']);
            Route::get('/booking/{id}', [MyApiController::class, 'booking']);
            Route::get('/booking/android/{id}', [MyApiController::class, 'bookingAndroid']);
            Route::get('/journey', [MyApiController::class, 'journey']);
            Route::get('/journey/{id}', [MyApiController::class, 'viewJourney']);
            Route::get('/notifications', [MyApiController::class, 'notifications']);
            Route::post('/notifications/read', [MyApiController::class, 'deleteNotifications']);
            Route::post('/notifications/read/all', [MyApiController::class, 'readAllNotification']);
            Route::get('/favourite/vehicles', [MyApiController::class, 'favouriteVehicles']);
        });

        Route::prefix('my')->group(function () {
            Route::post('/device-id', [MyApiController::class, 'updateDeviceId']);
            Route::get('/profile', [MyApiController::class, 'profile']);
            Route::put('profile/update', [MyApiController::class, 'update']);
            Route::post('update-profile', [MyApiController::class, 'updateProfile']);
            Route::post('/email/change', [MyApiController::class, 'changeEmail']);
            Route::post('/mobile/change', [MyApiController::class, 'changeMobile']);
            Route::post('/password/change', [MyApiController::class, 'changePassword']);
            Route::post('/profile/upload', [MyApiController::class, 'upload']);
            Route::post('/profile/upload/procedural', [MyApiController::class, 'uploadProcedural']);
            Route::get('/bookings', [MyApiController::class, 'bookings']);
            Route::get('/cancellations', [MyApiController::class, 'cancellations']);
            Route::get('/activities', [MyApiController::class, 'activities']);
            Route::get('/booking/{id}', [MyApiController::class, 'booking']);
            Route::get('/booking/android/{id}', [MyApiController::class, 'bookingAndroid']);
            Route::get('/journey', [MyApiController::class, 'journey']);
            Route::get('/journey/{id}', [MyApiController::class, 'viewJourney']);
            Route::get('/notifications', [MyApiController::class, 'notifications']);
            Route::post('/notifications/read', [MyApiController::class, 'deleteNotifications']);
            Route::post('/notifications/read/all', [MyApiController::class, 'readAllNotification']);
            Route::get('/favourite/launches', [MyApiController::class, 'favouriteVehicles']);
        });

        Route::prefix('quickbook')->group(function () {
            Route::get('/booking/{id}', [QuickBookController::class, 'getBookingByID']);
            Route::get('/routes', [QuickBookController::class, 'routes']);
            Route::get('/search', [QuickBookController::class, 'search']);
            Route::get('/trip/{id}', [QuickBookController::class, 'trip']);
            Route::post('/confirm', [QuickBookController::class, 'confirm']);
            Route::post('/payment', [QuickBookController::class, 'fullPaid']);
            Route::post('/cart/add', [QuickBookController::class, 'addToCart']);
            Route::post('/cart/add/deck', [QuickBookController::class, 'addToCartDeck']);
            Route::get('/find', [QuickBookController::class, 'findBookings']);
            Route::get('/qr', [QuickBookController::class, 'qrScan']);
            Route::post('/printed', [QuickBookController::class, 'printConfirm']);
            Route::post('/reprint', [QuickBookController::class, 'rePrintRequest']);
            Route::post('/print/all', [QuickBookController::class, 'printAll']);
            Route::post('/reprint/confirm', [QuickBookController::class, 'rePrintConfirm']);
            Route::get('/details/{id}', [QuickBookController::class, 'details']);
            Route::post('/deck', [QuickBookController::class, 'quickPrint']);
        });

        Route::prefix('supervisor')->group(function () {
            Route::get('/', [SupervisorController::class, 'profile']);
            Route::get('/jobs', [SupervisorController::class, 'jobs']);
            Route::get('/booking/history', [SupervisorController::class, 'bookingHistory']);
            Route::get('/cart', [SupervisorController::class, 'myCart']);
            Route::post('/cart/send', [SupervisorController::class, 'sendMyCart']);
            Route::get('/booking/group-wize-print', [SupervisorController::class, 'bookingGroupWizePrint']);
            Route::get('/scan/history', [SupervisorController::class, 'scanHistory']);
            Route::get('/vehicles', [SupervisorController::class, 'vehicles']);
            Route::get('/schedules', [SupervisorController::class, 'schedules']);
            Route::get('/available/vehicle', [SupervisorController::class, 'destinationVehicles']);
            Route::get('/wallet', [SupervisorController::class, 'wallet']);
            Route::get('/summary', [SupervisorController::class, 'summaryReport']);
            Route::post('/summary/send', [SupervisorController::class, 'sendSummary']);
            Route::post('booking/cancel', [ApiCancellationController::class, 'store']);
            Route::get('/cancellations', [ApiCancellationController::class, 'index']);
            Route::get('/cancellations/{id}/show', [ApiCancellationController::class, 'show']);
            Route::put('/cancellations/{id}/update', [ApiCancellationController::class, 'update']);
        });
    });
});
