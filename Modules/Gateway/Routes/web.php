<?php

use Illuminate\Support\Facades\Route;
use Modules\Gateway\Http\Controllers\GatewayController;

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

Route::group(['prefix' => 'dashboard', 'middleware' => 'auth'], function() {
    Route::resource('credential', 'GatewayCredentialController')
        ->only(['store', 'destroy'])
        ->names('gateway.credential');

    Route::resource('endpoint', 'GatewayEndpointController')
        ->only(['store', 'destroy'])
        ->names('gateway.endpoint');

    Route::get('suggestion', [GatewayController::class, 'suggestion'])->name('gateway.suggestion');

    Route::resource('gateway', 'GatewayController')->except(['destroy']);
});
