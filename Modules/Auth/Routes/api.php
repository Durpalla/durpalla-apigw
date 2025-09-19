<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('resend/{reference}', [AuthController::class, 'resendOtp']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');

    Route::group(['middleware' => 'auth:api'], function() {
        Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    });
});
