<?php
use App\Http\Controllers\PaymentAccountController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentAccountTransferController;
use App\Http\Controllers\PaymentAccountDepositController;

Route::get('/payment-accounts/transfers/options', [PaymentAccountTransferController::class,'options'])->name('payment-accounts.transfers.options');
Route::post('/payment-accounts/transfers', [PaymentAccountTransferController::class,'store'])->name('payment-accounts.transfers.store');
Route::get('/payment-accounts/transfers/{transfer}/document', [PaymentAccountTransferController::class,'document'])->name('payment-accounts.transfers.document');
Route::post('/payment-accounts/deposits', [PaymentAccountDepositController::class,'store'])->name('payment-accounts.deposits.store');

Route::controller(PaymentAccountController::class)->prefix('payment-accounts')->name('payment-accounts.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/types/options', 'typeOptions')->name('types.options');
    Route::get('/{paymentAccount}/book', 'book')->name('book');
    Route::put('/{paymentAccount}/opening-balance', 'updateOpeningBalance')->name('opening-balance');
    Route::post('/', 'store')->name('store');
    Route::put('/{paymentAccount}', 'update')->name('update');
    Route::delete('/{paymentAccount}', 'destroy')->name('destroy');
    Route::put('/{paymentAccount}/close', 'close')->name('close');
    Route::post('/types', 'storeType')->name('types.store');
    Route::put('/types/{paymentAccountType}', 'updateType')->name('types.update');
    Route::delete('/types/{paymentAccountType}', 'destroyType')->name('types.destroy');
});
