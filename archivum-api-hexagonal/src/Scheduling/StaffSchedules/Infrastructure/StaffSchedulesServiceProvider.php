<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\StaffSchedules\Domain\Contracts\StaffScheduleRepository;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\EloquentStaffScheduleRepository;

final class StaffSchedulesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        StaffScheduleRepository::class => EloquentStaffScheduleRepository::class,
    ];
}
