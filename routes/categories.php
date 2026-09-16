<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/taxonomies', '/categories');

Route::controller(CategoryController::class)->prefix('categories')->name('products.categories.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{record}', 'update')->name('update');
    Route::delete('/{record}', 'destroy')->name('destroy');
});
