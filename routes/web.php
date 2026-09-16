<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Authenticated ERP routes
|--------------------------------------------------------------------------
|
| Each business area owns its route definitions. Keeping the authentication
| boundary here guarantees that a newly added module cannot accidentally
| expose an ERP endpoint without authentication.
|
*/
Route::middleware('auth')->group(function () {
    require __DIR__.'/home.php';
    require __DIR__.'/users.php';
    require __DIR__.'/roles.php';

    // Specific product paths must be registered before model-bound paths.
    require __DIR__.'/serial-numbers.php';
    require __DIR__.'/products.php';
    require __DIR__.'/units.php';
    require __DIR__.'/categories.php';
    require __DIR__.'/brands.php';
    require __DIR__.'/variations.php';
    require __DIR__.'/warranties.php';

    // Specific contact paths must be registered before /contacts/{type}.
    require __DIR__.'/contact-imports.php';
    require __DIR__.'/customer-groups.php';
    require __DIR__.'/contacts.php';
});
