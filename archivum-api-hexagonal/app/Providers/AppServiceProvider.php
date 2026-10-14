<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

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
        Date::serializeUsing(fn (CarbonInterface $date): string => $date->toIso8601String());
    }
}
