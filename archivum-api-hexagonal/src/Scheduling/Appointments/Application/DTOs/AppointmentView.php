<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

use DateTimeImmutable;

/** One appointment as the customer reads it, with the store, the service, the person and the perfumes to try. */
final readonly class AppointmentView
{
    /**
     * @param  list<array{id: string, name: string}>  $perfumes
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $businessId,
        public string $businessName,
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
