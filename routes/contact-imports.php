<?php

use App\Http\Controllers\ContactImportController;
use Illuminate\Support\Facades\Route;

Route::controller(ContactImportController::class)->prefix('contacts/import')->name('contacts.import.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/template', 'template')->name('template');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
});
