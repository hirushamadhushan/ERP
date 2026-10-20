<?php

use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('sales')->name('sales.')->controller(SalesOrderController::class)->group(function(){
    Route::get('/', 'index')->middleware('role.permission:invoice.view')->name('index');
    Route::get('/create', 'create')->middleware('role.permission:invoice.create')->name('create');
    Route::post('/', 'store')->middleware('role.permission:invoice.create')->name('store');
});

Route::prefix('invoices')->name('invoices.')->controller(SalesInvoiceController::class)->group(function () {
    Route::get('/', 'index')->middleware('role.permission:invoice.view')->name('index');
    Route::get('/create', 'create')->middleware('role.permission:invoice.create')->name('create');
    Route::post('/', 'store')->middleware('role.permission:invoice.create')->name('store');
    Route::get('/{invoice}/edit', 'edit')->middleware('role.permission:invoice.view')->name('edit');
    Route::put('/{invoice}', 'update')->middleware('role.permission:invoice.edit')->name('update');
    Route::post('/{invoice}/post', 'post')->middleware('role.permission:invoice.post')->name('post');
    Route::post('/{invoice}/reset-draft', 'resetDraft')->middleware('role.permission:invoice.post')->name('reset-draft');
    Route::post('/{invoice}/payments', 'payment')->middleware('role.permission:invoice.payment')->name('payments.store');
    Route::post('/{invoice}/credits', 'applyCredit')->middleware('role.permission:invoice.credit')->name('credits.apply');
    Route::post('/{invoice}/credit-notes', 'creditNote')->middleware('role.permission:invoice.credit')->name('credit-notes.store');
    Route::get('/{invoice}/credit-notes/{creditNote}', 'creditNoteShow')->middleware('role.permission:invoice.view')->name('credit-notes.show');
    Route::get('/{invoice}/credit-notes/{creditNote}/pdf', 'creditNotePdf')->middleware('role.permission:invoice.view')->name('credit-notes.pdf');
    Route::post('/{invoice}/send', 'send')->middleware('role.permission:invoice.send')->name('send');
    Route::post('/{invoice}/activities', 'activity')->middleware('role.permission:invoice.edit')->name('activities.store');
    Route::get('/{invoice}/print', 'print')->middleware('role.permission:invoice.view')->name('print');
    Route::get('/{invoice}/pdf', 'pdf')->middleware('role.permission:invoice.view')->name('pdf');
});
