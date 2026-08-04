<?php

use App\Http\Controllers\FrontController;
use App\Http\Controllers\GatewayCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [FrontController::class, 'index'])->name('front.index');

// Named route required by Laravel auth redirect; API returns JSON
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Unauthenticated. Use POST /api/v1/customer/auth/login or send Authorization: Bearer <token>.',
    ], 401);
})->name('login');

Route::get('gateway/{gateway}/callback', [GatewayCallbackController::class, 'callback'])
    ->name('gateway.callback');


Route::get('payment/status', [GatewayCallbackController::class, 'paymentStatus'])->name('payment.status');

Route::get('/download/{id}', [FrontController::class, 'downloadInvoice'])
    ->name('invoice.download')
    ->middleware('signed');

Route::get('/invoice/{id}', [FrontController::class, 'viewInvoice'])
    ->name('invoice.view')
    ->middleware('signed');
