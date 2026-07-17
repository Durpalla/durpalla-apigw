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
Route::group(['prefix' => 'v1', 'middleware' => ['throttle:100,1', 'JsonResponse']], function () {
    include(__DIR__ . '/api/v1.php');
});

// v2 same as v1 (for clients that call /api/v2/search etc.)
Route::group(['prefix' => 'v2', 'middleware' => ['throttle:100,1', 'JsonResponse']], function () {
    include(__DIR__ . '/api/v1.php');
});

// Reseller booking API (Passport client-credentials) — /api/reseller/v1/*
Route::group(['middleware' => ['throttle:100,1', 'JsonResponse']], function () {
    include(__DIR__ . '/api/reseller.php');
});
