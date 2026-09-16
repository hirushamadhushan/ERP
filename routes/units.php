<?php

use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::controller(UnitController::class)->prefix('units')->name('products.units.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{unit}', 'update')->name('update');
    Route::delete('/{unit}', 'destroy')->name('destroy');
});
