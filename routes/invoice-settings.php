<?php
use App\Http\Controllers\InvoiceSettingController;
use Illuminate\Support\Facades\Route;
Route::controller(InvoiceSettingController::class)->prefix('invoice-settings')->name('business.invoice-settings.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{invoiceScheme}', 'update')->name('update');
    Route::delete('/{invoiceScheme}', 'destroy')->name('destroy');
    Route::get('/layouts/create', 'createLayout')->name('layouts.create');
    Route::post('/layouts', 'storeLayout')->name('layouts.store');
    Route::get('/layouts/{invoiceLayout}/edit', 'editLayout')->name('layouts.edit');
    Route::put('/layouts/{invoiceLayout}', 'updateLayout')->name('layouts.update');
});
