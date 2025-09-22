<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\BkashController;

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


Route::prefix('payment')->group(function() {
    Route::get('/', 'PaymentController@index');
});

Route::group(['prefix' => 'bkash'], function () {
    Route::post('pay',[BkashController::class, 'pay'])->name('bkash.pay');

    // bKash will hit these after customer completes/aborts on their page
    Route::get('callback', [BkashController::class, 'callback'])->name('bkash.callback');

    // optional
    Route::post('refund', [BkashController::class, 'refund'])->name('bkash.refund');
});
