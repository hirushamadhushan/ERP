<?php

use App\Http\Controllers\SerialNumberController;
use Illuminate\Support\Facades\Route;

Route::controller(SerialNumberController::class)
    ->prefix('products/serial-numbers')
    ->name('products.serials.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/report', 'report')->name('report');
        Route::post('/preview', 'preview')->name('preview');
        Route::post('/', 'store')->name('store');
        Route::delete('/', 'destroy')->name('destroy');
        Route::post('/references', 'reference')->name('reference');
        Route::get('/template', 'template')->name('template');
        Route::post('/import', 'import')->middleware('throttle:10,1')->name('import');
    });
