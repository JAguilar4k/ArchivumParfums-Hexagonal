<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Repositories\EloquentAppointmentRepository;

final class AppointmentsServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> */
    public array $bindings = [
        AppointmentRepository::class => EloquentAppointmentRepository::class,
    ];
}
