<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::controller(UserController::class)->prefix('users')->name('users.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{user}/view', 'view')->name('view');
    Route::put('/{user}/profile', 'updateProfile')->name('profile.update');
    Route::post('/{user}/documents', 'storeDocument')->name('documents.store');
    Route::post('/{user}/notes', 'storeNote')->name('notes.store');
    Route::get('/documents/{document}/download', 'downloadDocument')->name('documents.download');
    Route::get('/{id}', 'show')->name('show');
    Route::get('/{id}/edit', 'edit')->name('edit');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
});
