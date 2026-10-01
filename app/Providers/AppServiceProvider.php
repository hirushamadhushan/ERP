<?php

namespace App\Providers;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A safe fallback lets maintenance, migration and error views render
        // before the business_settings table exists.
        View::share('businessSetting', new BusinessSetting(['business_name' => 'Codeza ERP']));
        View::composer('auth.login', function () {
            View::share('businessSetting', BusinessSetting::current());
        });

        /*
         * Time-sensitive screens and reports must use the business-selected
         * timezone, rather than only the server's APP_TIMEZONE. The schema
         * guard keeps migrations and first-time installation independent of
         * this optional settings table.
         */
        try {
            if (! Schema::hasTable('business_settings')) {
                return;
            }

            // Share this identity record so settings control the visible
            // business name and logo across the authenticated application.
            $businessSetting = BusinessSetting::query()->first() ?? BusinessSetting::current();
            View::share('businessSetting', $businessSetting);

            $timezone = $businessSetting->time_zone;
            if ($timezone && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        } catch (\Throwable) {
            // Settings must never prevent CLI setup or error rendering.
        }
    }
}
