<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function () {
    Route::get('/', 'index')->middleware('role.permission:role.view')->name('index');
    Route::post('/', 'store')->middleware('role.permission:role.add')->name('store');
    Route::get('/{id}/edit', 'edit')->middleware('role.permission:role.edit')->name('edit');
    Route::put('/{id}', 'update')->middleware('role.permission:role.edit')->name('update');
    Route::delete('/{id}', 'destroy')->middleware('role.permission:role.delete')->name('destroy');
});
