<?php

use App\Http\Controllers\GroupTaxController;
use App\Http\Controllers\TaxRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('tax-rates')->name('business.tax-rates.')->group(function () {
    Route::get('/', [TaxRateController::class, 'index'])->name('index');
    Route::post('/', [TaxRateController::class, 'store'])->name('store');
    Route::put('/{taxRate}', [TaxRateController::class, 'update'])->name('update');
    Route::delete('/{taxRate}', [TaxRateController::class, 'destroy'])->name('destroy');
    Route::post('/groups', [GroupTaxController::class, 'store'])->name('groups.store');
    Route::put('/groups/{taxGroup}', [GroupTaxController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{taxGroup}', [GroupTaxController::class, 'destroy'])->name('groups.destroy');
});
