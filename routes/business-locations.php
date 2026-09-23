<?php

use App\Http\Controllers\BusinessLocationController;
use Illuminate\Support\Facades\Route;

Route::controller(BusinessLocationController::class)->prefix('business-locations')->name('business.locations.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{location}', 'update')->name('update');
    Route::patch('/{location}/status', 'toggle')->name('toggle');
    Route::get('/check-code', 'checkCode')->name('check-code');
    Route::get('/{location}/settings', 'settings')->name('settings');
    Route::put('/{location}/settings', 'updateSettings')->name('settings.update');
});
