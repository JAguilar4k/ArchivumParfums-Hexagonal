<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentNotFoundException;

/** Read one appointment of the account of the session. */
final readonly class GetMyAppointment
{
    /** Receive the reader of the appointments of a customer. */
    public function __construct(private CustomerAppointments $appointments) {}

    /**
     * The appointment of the customer; one of another customer and one that does not exist are the same 404.
     *
     * @throws AppointmentNotFoundException
     */
    public function execute(string $id, string $userId): AppointmentView
    {
        return $this->appointments->find($id, $userId) ?? throw AppointmentNotFoundException::create();
    }
}
