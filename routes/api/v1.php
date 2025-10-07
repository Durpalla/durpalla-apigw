<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'customer'], function () {
    include(__DIR__ . '/v1/customer.php');
});

Route::group(['prefix' => 'customer'], function () {
    include(__DIR__ . '/v1/supervisor.php');
});

Route::group(['prefix' => 'merchant'], function () {
    include(__DIR__ . '/v1/merchant.php');
});
