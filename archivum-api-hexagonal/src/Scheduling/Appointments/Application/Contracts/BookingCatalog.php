<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

use Src\Scheduling\Appointments\Application\DTOs\BookingTerms;
use Src\Scheduling\Appointments\Application\DTOs\PersonOption;
use Src\Scheduling\Appointments\Application\DTOs\ServiceOption;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Port that answers what the store offers to book: its terms of the booking window, its services, its people who
 * attend appointments and the perfumes a trial may try. Every query is limited to the business of the context.
 */
interface BookingCatalog
{
    /** The time zone of the store and the hours of notice and days of horizon of its booking window. */
    public function terms(): BookingTerms;

    /** The service when it is active in the store of the context; null when it is inactive or belongs to another store. */
    public function service(ServiceId $id): ?ServiceOption;

    /** The person when they attend appointments in the store of the context; otherwise null. */
    public function person(MemberId $id): ?PersonOption;

    /**
     * The perfumes of the store that are active, keyed by identity and with the name the customer reads.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function perfumes(array $ids): array;

    /** The place the store configured for its appointments, or null when it did not write one. */
    public function appointmentPlace(): ?string;
}
