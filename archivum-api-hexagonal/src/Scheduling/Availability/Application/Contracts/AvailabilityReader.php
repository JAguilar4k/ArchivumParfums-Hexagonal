<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\Contracts;

use DateTimeImmutable;
use Src\Scheduling\Availability\Application\DTOs\AvailableService;
use Src\Scheduling\Availability\Application\DTOs\PersonAvailability;
use Src\Scheduling\Availability\Application\DTOs\StoreAvailability;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Port that brings, in a few queries, everything the availability of one day needs: the terms of the store, the
 * service the visitor wants to book, the opening hours of that weekday and the people who can attend it.
 *
 * Every query reaches only the rows of the business pinned in the transaction, and the row-level policies are the
 * second barrier.
 */
interface AvailabilityReader
{
    /** The store of the context with the terms of its booking window. */
    public function store(): StoreAvailability;

    /** The service when it is active in the store of the context; null when it is inactive or belongs to another store. */
    public function service(ServiceId $id): ?AvailableService;

    /**
     * The tramos the store opens that weekday.
     *
     * @return list<TimeRange>
     */
    public function businessHours(Weekday $weekday): array;

    /**
     * The people who attend appointments and may work that day, each one with their tramos, their blocks and their
     * active appointments. When a member is given, only that person; an empty list means the person is unknown, does
     * not attend appointments or belongs to another store. The appointment being moved is left out of the occupied
     * range, so rescheduling it does not compete with itself (#65).
     *
     * @return list<PersonAvailability>
     */
    public function people(Weekday $weekday, DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, ?MemberId $only, ?string $exceptAppointment = null): array;
}
