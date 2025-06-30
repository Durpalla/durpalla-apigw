<?php

use Illuminate\Support\Facades\Route;

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

Route::group(['prefix' => 'v2/customer', 'middleware' => ['cors', 'throttle:100,1', 'JsonResponse']], function(){
    Route::post('want-to-be-partner', 'CustomerApiController@wantTobePartner');
});
