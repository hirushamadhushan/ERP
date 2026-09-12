<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\ContactImportController;

// Root redirect
Route::get('/', function () {
    return redirect()->route('login');
});

// Protected ERP routes — require authentication
Route::middleware('auth')->group(function () {

    // Home / Dashboard
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Redirect /dashboard -> /home
    Route::get('/dashboard', function () {
        return redirect()->route('home');
    });

    // User Management Suite
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

    // Roles Management
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Contacts
    Route::get('/contacts/import', [ContactImportController::class, 'index'])->name('contacts.import.index');
    Route::get('/contacts/import/template', [ContactImportController::class, 'template'])->name('contacts.import.template');
    Route::post('/contacts/import', [ContactImportController::class, 'store'])->middleware('throttle:10,1')->name('contacts.import.store');
    Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('contacts.groups.index');
    Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->name('contacts.groups.store');
    Route::put('/customer-groups/{group}', [CustomerGroupController::class, 'update'])->name('contacts.groups.update');
    Route::delete('/customer-groups/{group}', [CustomerGroupController::class, 'destroy'])->name('contacts.groups.destroy');
    Route::redirect('/contacts', '/contacts/customer');
    Route::get('/contacts/{type}', [ContactController::class, 'index'])->whereIn('type', ['customer', 'supplier', 'commission'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('/contacts/records/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/contacts/records/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/records/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');



});
