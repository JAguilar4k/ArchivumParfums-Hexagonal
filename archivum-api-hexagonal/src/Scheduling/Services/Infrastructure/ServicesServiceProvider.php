<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Services\Application\Contracts\BookableServices;
use Src\Scheduling\Services\Domain\Contracts\ServiceRepository;
use Src\Scheduling\Services\Infrastructure\Persistence\EloquentBookableServices;
use Src\Scheduling\Services\Infrastructure\Persistence\EloquentServiceRepository;

final class ServicesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ServiceRepository::class => EloquentServiceRepository::class,
        BookableServices::class => EloquentBookableServices::class,
    ];
}
