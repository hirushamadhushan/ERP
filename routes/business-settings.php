<?php

use App\Http\Controllers\BusinessSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/business/settings', [BusinessSettingController::class, 'index'])->name('business.settings.index');
Route::post('/business/settings', [BusinessSettingController::class, 'update'])->name('business.settings.update');
