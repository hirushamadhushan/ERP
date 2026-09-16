<?php

use App\Http\Controllers\BrandController;
use Illuminate\Support\Facades\Route;

Route::controller(BrandController::class)->prefix('brands')->name('products.brands.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{record}', 'update')->name('update');
    Route::delete('/{record}', 'destroy')->name('destroy');
});
