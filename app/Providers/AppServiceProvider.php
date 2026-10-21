<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\AppSetting;

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
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('app_settings')) {
                $settings = AppSetting::pluck('value', 'key')->toArray();
                View::share('globalAppSettings', $settings);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('periods')) {
                $periods = \App\Models\Period::orderBy('period', 'desc')->pluck('period')->toArray();
                View::share('globalPeriods', $periods);
            }
        } catch (\Throwable $e) {
            // ignore during migrate
        }
    }
}
