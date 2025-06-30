<?php
use Illuminate\Support\Facades\Route;

Route::prefix('admin/setting')->group(function() {
    Route::resource('broadcast', 'BroadCastController');
});
