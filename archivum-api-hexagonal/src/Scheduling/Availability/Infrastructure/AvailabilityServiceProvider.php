<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Availability\Application\Contracts\AvailabilityReader;
use Src\Scheduling\Availability\Infrastructure\Persistence\EloquentAvailabilityReader;

final class AvailabilityServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> */
    public array $bindings = [
        AvailabilityReader::class => EloquentAvailabilityReader::class,
    ];
}
