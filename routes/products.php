<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductLotController;
use Illuminate\Support\Facades\Route;

Route::controller(ProductController::class)->prefix('products')->name('products.catalog.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/quick-reference', 'quickReference')->middleware('throttle:60,1')->name('reference');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{product}/edit', 'edit')->name('edit');
    Route::post('/{product}/duplicate', 'duplicate')->name('duplicate');
    Route::put('/{product}', 'update')->name('update');
    Route::delete('/{product}', 'destroy')->name('destroy');
    Route::get('/{product}/opening-stock', 'opening')->name('opening');
    Route::post('/{product}/opening-stock', 'saveOpening')->name('opening.store');
    Route::get('/{product}/lots', [ProductLotController::class, 'index'])->name('lots.index');
    Route::post('/{product}/lots', [ProductLotController::class, 'store'])->name('lots.store');
    Route::get('/{product}/selling-prices', 'prices')->name('prices');
    Route::put('/{product}/selling-prices', 'savePrices')->name('prices.update');
    Route::get('/{product}/files/{kind}', 'attachment')->name('attachment');
});
