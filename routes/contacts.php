<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::redirect('/contacts', '/contacts/customer');

Route::controller(ContactController::class)->prefix('contacts')->name('contacts.')->group(function () {
    Route::get('/records/{contact}', 'show')->name('show');
    Route::put('/records/{contact}', 'update')->name('update');
    Route::delete('/records/{contact}', 'destroy')->name('destroy');
    Route::post('/', 'store')->name('store');
    Route::get('/{type}', 'index')
        ->whereIn('type', ['customer', 'supplier', 'commission'])
        ->name('index');
});
