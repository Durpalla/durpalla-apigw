<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\RoleController;
use Modules\Auth\Http\Controllers\UserController;

Route::group(['prefix' => 'auth', 'middleware' => 'guest'], function() {
    Route::get('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('login', [AuthController::class, 'check'])->name('auth.login.post');

    //SMS - Email OTP Route
    Route::get('2fa', [AuthController::class, 'otp'])->name('auth.2fa');
    Route::post('2fa', [AuthController::class, 'verify'])->name('auth.2fa.post');

    //Authenticator Verify Route
    Route::get('verify', [AuthController::class, 'googleAuthy'])->name('auth.authy');

    //Reset-Password Routes
    Route::get('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('reset-otp', [AuthController::class, 'resetOtp'])->name('auth.reset-otp');
    Route::get('get-otp/{token}', [AuthController::class, 'getOtp'])->name('auth.get-otp');
    Route::post('verify-reset-otp', [AuthController::class, 'verifyResetOtp'])->name('auth.verify-reset-otp');
    Route::get('reset-password/{token}', [AuthController::class, 'getResetPassword'])->name('auth.get-reset-password');
    Route::put('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::get('reset', [AuthController::class, 'reset'])->name('password.reset');
});

Route::prefix('dashboard')
    ->namespace('\Modules\Auth\Http\Controllers')
    ->middleware('auth:web')
    ->group(function () {
        Route::get('role/suggestion', [RoleController::class, 'suggestions'])->name('role.suggestion');

        Route::prefix('auth')->group(function () {
            Route::get('profile', [AuthController::class, 'profile'])->name('auth.profile');
            Route::get('change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
            Route::put('update-password', [AuthController::class, 'updatePassword'])->name('auth.update-password');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');

            Route::group(['prefix' => 'user'], function () {
                Route::get('suggestion', [UserController::class, 'suggestions'])->name('user.suggestion');
                Route::put('{user}/status-update', [UserController::class, 'updateStatus'])->name('user.status.update');
                Route::resource('access-block', 'UserAccessBlockController')->only(['index', 'update', 'destroy']);
            });

            Route::resources([
                'user' => 'UserController',
                'role' => 'RoleController',
                'permission' => 'PermissionController',
            ]);
        });
    });
