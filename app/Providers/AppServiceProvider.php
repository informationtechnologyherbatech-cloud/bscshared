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
        // Satu konteks entitas per siklus permintaan: memoisasinya berlaku untuk
        // seluruh kueri dalam permintaan itu, dan penggantian manual (konsol/tes)
        // tidak hilang di tengah jalan.
        $this->app->scoped(\App\Support\EntityContext::class);
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
        } catch (\Throwable $e) {
            // ignore during migrate
        }
    }
}
