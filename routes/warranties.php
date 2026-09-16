<?php

use App\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

Route::controller(WarrantyController::class)->prefix('warranties')->name('products.warranties.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{warranty}', 'update')->name('update');
    Route::delete('/{warranty}', 'destroy')->name('destroy');
});
