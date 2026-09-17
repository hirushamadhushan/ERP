<?php

use App\Http\Controllers\BusinessLocationController;
use Illuminate\Support\Facades\Route;

Route::controller(BusinessLocationController::class)->prefix('business-locations')->name('business.locations.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{location}', 'update')->name('update');
    Route::patch('/{location}/status', 'toggle')->name('toggle');
});
