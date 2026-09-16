<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::controller(ProductController::class)->prefix('products')->name('products.catalog.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/quick-reference', 'quickReference')->name('reference');
    Route::get('/{product}/edit', 'edit')->name('edit');
    Route::put('/{product}', 'update')->name('update');
    Route::delete('/{product}', 'destroy')->name('destroy');
    Route::get('/{product}/opening-stock', 'opening')->name('opening');
    Route::post('/{product}/opening-stock', 'saveOpening')->name('opening.store');
    Route::get('/{product}/files/{kind}', 'attachment')->name('attachment');
});
