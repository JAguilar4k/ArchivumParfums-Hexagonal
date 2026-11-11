<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** What the customer asked to book, already validated in shape by the request. */
final readonly class BookAppointmentCommand
{
    /**
     * @param  list<string>  $perfumes  The perfumes the trial will try, in the order the customer chose them.
     */
    public function __construct(
        public string $userId,
        public string $serviceId,
        public string $startsAt,
        public ?string $memberId,
        public array $perfumes,
    ) {}
}
