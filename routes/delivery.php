<?php

use App\Http\Controllers\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::prefix('delivery')->name('delivery.')->middleware('role.permission:delivery.view')->controller(DeliveryController::class)->group(function () {
    Route::get('/', fn () => redirect()->route('delivery.vehicles.index'));
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
        Route::get('/transfer', 'transferForm')->name('transfers.create');
        Route::get('/stock-options', 'options')->name('options');
        Route::post('/transfer', 'transfer')->middleware('throttle:30,1')->name('transfers.store');
    });
});
