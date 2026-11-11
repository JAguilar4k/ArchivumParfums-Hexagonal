<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Application\UseCases;

use Src\Scheduling\StaffSchedules\Domain\Contracts\StaffScheduleRepository;
use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;
use Src\Scheduling\StaffSchedules\Domain\Exceptions\MemberNotFoundException;
use Src\Shared\Domain\ValueObjects\MemberId;

/** Read the working hours of one person of the staff of the session business. */
final readonly class GetStaffSchedule
{
    /** Receive the repository. */
    public function __construct(private StaffScheduleRepository $schedules) {}

    /**
     * The tramos of that person, or not found when she is not staff of this business.
     *
     * @throws MemberNotFoundException
     */
    public function execute(string $memberId): StaffSchedule
    {
        $member = new MemberId($memberId);

        if ($this->schedules->memberAttendsAppointments($member) === null) {
            throw MemberNotFoundException::create();
        }

        return $this->schedules->ofMember($member);
    }
}
