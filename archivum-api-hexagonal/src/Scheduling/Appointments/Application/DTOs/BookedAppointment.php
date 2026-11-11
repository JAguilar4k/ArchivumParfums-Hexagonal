<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

use DateTimeImmutable;

/** The appointment that was just booked, with everything the API answers about it. */
final readonly class BookedAppointment
{
    /**
     * @param  list<array{id: string, name: string}>  $perfumes  The perfumes the trial will try, in the chosen order.
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $serviceId,
        public string $serviceName,
        public string $memberId,
        public string $memberName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public ?string $place,
        public array $perfumes,
    ) {}
}
