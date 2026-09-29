<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::redirect('/contacts', '/contacts/customer');

Route::controller(ContactController::class)->prefix('contacts')->name('contacts.')->group(function () {
    Route::get('/customers/{contact}/ledger', 'ledger')->name('customers.ledger');
    Route::get('/customers/{contact}/sales-history', 'salesHistory')->name('customers.sales-history');
    Route::post('/customers/{contact}/due-payments', 'collectDue')->name('customers.due-payments');
    Route::get('/customers/{contact}/documents', 'documents')->name('customers.documents');
    Route::post('/customers/{contact}/documents', 'storeDocument')->name('customers.documents.store');
    Route::post('/customers/{contact}/notes', 'storeNote')->name('customers.notes.store');
    Route::get('/customer-documents/{document}/download', 'downloadDocument')->name('customers.documents.download');
    Route::put('/customers/{contact}/status', 'toggleStatus')->name('customers.status');
    Route::get('/records/{contact}', 'show')->name('show');
    Route::put('/records/{contact}', 'update')->name('update');
    Route::delete('/records/{contact}', 'destroy')->name('destroy');
    Route::post('/', 'store')->name('store');
    Route::get('/{type}', 'index')
        ->whereIn('type', ['customer', 'supplier', 'commission'])
        ->name('index');
});
