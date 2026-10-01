<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::controller(UserController::class)->prefix('users')->name('users.')->group(function () {
    Route::get('/', 'index')->middleware('role.permission:user.view')->name('index');
    Route::get('/create', 'create')->middleware('role.permission:user.add')->name('create');
    Route::post('/', 'store')->middleware('role.permission:user.add')->name('store');
    Route::get('/{user}/view', 'view')->middleware('role.permission:user.view')->name('view');
    Route::put('/{user}/profile', 'updateProfile')->middleware('role.permission:user.edit')->name('profile.update');
    Route::post('/{user}/documents', 'storeDocument')->middleware('role.permission:user.edit')->name('documents.store');
    Route::post('/{user}/notes', 'storeNote')->middleware('role.permission:user.edit')->name('notes.store');
    Route::get('/documents/{document}/download', 'downloadDocument')->middleware('role.permission:user.view')->name('documents.download');
    Route::get('/{id}', 'show')->middleware('role.permission:user.view')->name('show');
    Route::get('/{id}/edit', 'edit')->middleware('role.permission:user.edit')->name('edit');
    Route::put('/{id}', 'update')->middleware('role.permission:user.edit')->name('update');
    Route::delete('/{id}', 'destroy')->middleware('role.permission:user.delete')->name('destroy');
});
