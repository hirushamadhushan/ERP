<?php

use App\Http\Controllers\CustomerGroupController;
use Illuminate\Support\Facades\Route;

Route::controller(CustomerGroupController::class)->prefix('customer-groups')->name('contacts.groups.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{group}', 'update')->name('update');
    Route::delete('/{group}', 'destroy')->name('destroy');
});
