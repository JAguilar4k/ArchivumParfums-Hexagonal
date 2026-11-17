<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/**
 * What was asked to book, already validated in shape by the request. The customer of the session books for itself and
 * the panel books for a customer record of its store, so each way has its own factory and the command always carries
 * exactly one of the two identities.
 */
final readonly class BookAppointmentCommand
{
    /**
     * @param  string|null  $userId  Account of the customer of the session; null when the panel books for a customer.
     * @param  list<string>  $perfumes  The perfumes the trial will try, in the order the customer chose them.
     * @param  string|null  $customerId  Customer record the panel chose; null when the customer books for itself.
     */
    private function __construct(
        public ?string $userId,
        public string $serviceId,
        public string $startsAt,
        public ?string $memberId,
        public array $perfumes,
        public ?string $customerId,
    ) {}

    /**
     * The booking the customer of the session makes for itself.
     *
     * @param  list<string>  $perfumes
     */
    public static function forAccount(string $userId, string $serviceId, string $startsAt, ?string $memberId, array $perfumes): self
    {
        return new self($userId, $serviceId, $startsAt, $memberId, $perfumes, null);
    }

    /**
     * The booking the panel makes for a customer record of its store.
     *
     * @param  list<string>  $perfumes
     */
    public static function forCustomer(string $customerId, string $serviceId, string $startsAt, ?string $memberId, array $perfumes): self
    {
        return new self(null, $serviceId, $startsAt, $memberId, $perfumes, $customerId);
    }
}
