<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{id}/edit', 'edit')->name('edit');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
});
