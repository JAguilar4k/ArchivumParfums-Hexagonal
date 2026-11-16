<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentPeriod;

/** List the appointments of the account of the session in the store of the context. */
final readonly class ListMyAppointments
{
    /** Receive the reader of the appointments of a customer. */
    public function __construct(private CustomerAppointments $appointments) {}

    /**
     * The appointments of the period, ordered as the customer reads them.
     *
     * @return list<AppointmentView>
     */
    public function execute(string $userId, AppointmentPeriod $period = AppointmentPeriod::Upcoming): array
    {
        return $this->appointments->of($userId, $period);
    }
}
