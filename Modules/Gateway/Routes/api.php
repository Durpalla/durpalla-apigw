<?php

use Illuminate\Support\Facades\Route;
use Modules\Gateway\Http\Controllers\Api\GatewayController;

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
Route::group(['prefix' => 'v2'], function () {
    Route::get('gateway', [GatewayController::class, 'index']);
});
