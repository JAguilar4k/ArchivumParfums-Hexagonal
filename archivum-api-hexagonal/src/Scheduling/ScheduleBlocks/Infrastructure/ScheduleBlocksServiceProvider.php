<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\EloquentScheduleBlockRepository;

final class ScheduleBlocksServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ScheduleBlockRepository::class => EloquentScheduleBlockRepository::class,
    ];
}
