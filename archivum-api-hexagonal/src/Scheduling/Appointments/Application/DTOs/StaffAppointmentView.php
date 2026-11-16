<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

use DateTimeImmutable;

/**
 * One appointment as the staff reads it in the agenda: the customer, the service, the person who attends it, both ends
 * and the perfumes to carry. The customer appears with the identity and the name the panel needs, without the contact
 * details the minimization of #110 keeps out of the agenda.
 */
final readonly class StaffAppointmentView
{
    /**
     * @param  list<array{id: string, brand: string, name: string}>  $perfumes
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $customerId,
        public string $customerName,
        public string $serviceId,
        public string $serviceName,
        public string $memberId,
        public string $memberName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public array $perfumes,
    ) {}
}
