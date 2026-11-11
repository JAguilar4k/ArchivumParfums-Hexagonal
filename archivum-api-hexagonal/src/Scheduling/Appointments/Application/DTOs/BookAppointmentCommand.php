<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** What was asked to book, already validated in shape by the request. */
final readonly class BookAppointmentCommand
{
    /**
     * @param  list<string>  $perfumes  The perfumes the trial will try, in the order the customer chose them.
     * @param  string|null  $userId  Account of the customer of the session; null when the panel books for a customer.
     * @param  string|null  $customerId  Customer record the panel chose; null when the customer books for itself.
     */
    public function __construct(
        public ?string $userId,
        public string $serviceId,
        public string $startsAt,
        public ?string $memberId,
        public array $perfumes,
        public ?string $customerId = null,
    ) {}
}
