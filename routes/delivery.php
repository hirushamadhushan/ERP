<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryConsignmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('delivery')->name('delivery.')->middleware('role.permission:delivery.view')->controller(DeliveryController::class)->group(function () {
    Route::get('/', fn () => redirect()->route('delivery.consignments.index'));
    Route::get('/vehicles', 'vehicles')->name('vehicles.index');
    Route::get('/drivers', 'drivers')->name('drivers.index');
    Route::get('/store', 'store')->name('store');
    Route::get('/transfers/{transfer}', 'receipt')->whereNumber('transfer')->name('transfers.show');
    Route::middleware('role.permission:delivery.manage')->group(function () {
        Route::get('/vehicles/create', 'vehicleForm')->name('vehicles.create');
        Route::get('/vehicles/{vehicle}/edit', 'vehicleForm')->name('vehicles.edit');
        Route::post('/vehicles', 'saveVehicle')->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', 'saveVehicle')->name('vehicles.update');
        Route::get('/drivers/create', 'driverForm')->name('drivers.create');
        Route::get('/drivers/{driver}/edit', 'driverForm')->name('drivers.edit');
        Route::post('/drivers', 'saveDriver')->name('drivers.store');
        Route::put('/drivers/{driver}', 'saveDriver')->name('drivers.update');
    });
    Route::middleware('role.permission:delivery.transfer')->group(function () {
        Route::get('/loading', 'loadingForm')->name('loading');
        Route::get('/unloading', 'unloadingForm')->name('unloading');
        Route::get('/transfer', 'transferForm')->name('transfers.create');
        Route::get('/stock-options', 'options')->name('options');
        Route::post('/transfer', 'transfer')->middleware('throttle:30,1')->name('transfers.store');
    });
});

Route::prefix('delivery')->name('delivery.consignments.')->middleware('role.permission:delivery.view')->controller(DeliveryConsignmentController::class)->group(function () {
    Route::get('/consignments', 'index')->name('index');
    Route::get('/consignments/{consignment}', 'show')->whereNumber('consignment')->name('show');
    Route::get('/consignments/{consignment}/proofs/{proof}', 'proof')->whereNumber('consignment')->whereNumber('proof')->name('proofs.show');
    Route::middleware('role.permission:delivery.transfer')->group(function () {
        Route::get('/consignments/create', 'create')->name('create');
        Route::post('/consignments', 'store')->middleware('throttle:30,1')->name('store');
        Route::post('/consignments/{consignment}/depart', 'depart')->middleware('throttle:30,1')->name('depart');
        Route::post('/consignments/{consignment}/arrive', 'arrive')->middleware('throttle:30,1')->name('arrive');
        Route::post('/consignments/{consignment}/return', 'completeReturn')->middleware('throttle:30,1')->name('return.complete');
        Route::post('/consignments/{consignment}/complete', 'complete')->middleware('throttle:30,1')->name('complete');
        Route::post('/consignments/{consignment}/proofs', 'uploadProof')->middleware('throttle:30,1')->name('proofs.store');
    });
});
