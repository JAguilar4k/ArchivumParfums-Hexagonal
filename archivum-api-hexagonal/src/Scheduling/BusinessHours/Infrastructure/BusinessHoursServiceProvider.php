<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\BusinessHours\Domain\Contracts\BusinessHoursRepository;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\EloquentBusinessHoursRepository;

final class BusinessHoursServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        BusinessHoursRepository::class => EloquentBusinessHoursRepository::class,
    ];
}
