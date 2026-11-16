<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Appointments\Application\Contracts\AppointmentCustomers;
use Src\Scheduling\Appointments\Application\Contracts\BookingAgenda;
use Src\Scheduling\Appointments\Application\Contracts\BookingCatalog;
use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Infrastructure\Accounts\AccountsAppointmentCustomers;
use Src\Scheduling\Appointments\Infrastructure\Catalog\CatalogBookingCatalog;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Queries\EloquentBookingAgenda;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Queries\PostgresCustomerAppointments;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Repositories\EloquentAppointmentRepository;

final class AppointmentsServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> */
    public array $bindings = [
        AppointmentRepository::class => EloquentAppointmentRepository::class,
        BookingCatalog::class => CatalogBookingCatalog::class,
        BookingAgenda::class => EloquentBookingAgenda::class,
        AppointmentCustomers::class => AccountsAppointmentCustomers::class,
        CustomerAppointments::class => PostgresCustomerAppointments::class,
    ];
}
