<?php

use App\Http\Controllers\VariationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/variations', '/variation-templates');

Route::controller(VariationController::class)
    ->prefix('variation-templates')
    ->name('products.variations.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{variation}', 'update')->name('update');
        Route::delete('/{variation}', 'destroy')->name('destroy');
    });
