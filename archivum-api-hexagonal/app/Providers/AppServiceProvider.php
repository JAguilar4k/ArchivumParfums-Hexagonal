<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Services\Domain\ServiceRepository;
use Src\Scheduling\Services\Infrastructure\Persistence\EloquentServiceRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ServiceRepository::class,
            EloquentServiceRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::serializeUsing(fn (CarbonInterface $date): string => $date->toIso8601String());
    }
}
