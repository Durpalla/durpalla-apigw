<?php

use Illuminate\Support\Facades\Route;
use Modules\FAQ\App\Http\Controllers\Api\FaqController;

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

Route::prefix('v2')->name('api.')->group(function () {
    Route::get('faq', [FaqController::class, 'index'])->name('faq');
});
