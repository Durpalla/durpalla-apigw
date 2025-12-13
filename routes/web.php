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

Route::get('gateway/{gateway}/callback', [GatewayCallbackController::class, 'callback'])
    ->name('gateway.callback');


Route::get('payment/{payment}/status', [GatewayCallbackController::class, 'paymentStatus'])->name('payment.status');

Route::get('/download/{id}', [FrontController::class, 'downloadInvoice'])
    ->name('invoice.download')
    ->middleware('signed');
