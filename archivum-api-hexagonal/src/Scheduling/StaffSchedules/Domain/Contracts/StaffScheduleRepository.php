<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain\Contracts;

use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;
use Src\Scheduling\StaffSchedules\Domain\ValueObjects\StaffScheduleId;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Port of the working hours of the staff of one business. Every query reaches only the rows of the business of the
 * context, and the row-level policy of PostgreSQL is the second barrier.
 */
interface StaffScheduleRepository
{
    /** Generate a time-ordered identity for a new schedule; the adapter owns the generation, the application does not. */
    public function nextId(): StaffScheduleId;

    /** Replace every tramo of that person in the business of the context, in one transaction. */
    public function replace(StaffSchedule $staffSchedule): void;

    /** The tramos of that person in the business of the context, ordered by weekday and starting hour. */
    public function ofMember(MemberId $member): StaffSchedule;

    /**
     * Whether that person of the staff attends appointments; null when the person does not exist in this business, so
     * a member of another business answers exactly like an unknown one.
     */
    public function memberAttendsAppointments(MemberId $member): ?bool;
}
