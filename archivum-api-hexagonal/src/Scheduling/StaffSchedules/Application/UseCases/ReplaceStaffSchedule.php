<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Application\UseCases;

use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Scheduling\StaffSchedules\Application\DTOs\ReplaceStaffScheduleCommand;
use Src\Scheduling\StaffSchedules\Domain\Contracts\StaffScheduleRepository;
use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;
use Src\Scheduling\StaffSchedules\Domain\Exceptions\MemberDoesNotAttendAppointmentsException;
use Src\Scheduling\StaffSchedules\Domain\Exceptions\MemberNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Replace the whole working hours of one person of the staff in one transaction. A person of another business answers as
 * not found, and a person who does not attend appointments is refused with a conflict before anything is written.
 */
final readonly class ReplaceStaffSchedule
{
    /** Receive the repository, the business of the session and the transaction ports. */
    public function __construct(
        private StaffScheduleRepository $schedules,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Build the schedule with the tramos of the command and store it, replacing the previous one.
     *
     * @throws MemberNotFoundException
     * @throws MemberDoesNotAttendAppointmentsException
     */
    public function execute(ReplaceStaffScheduleCommand $command): StaffSchedule
    {
        return $this->transaction->run(function () use ($command): StaffSchedule {
            $member = new MemberId($command->memberId);
            $attends = $this->schedules->memberAttendsAppointments($member);

            if ($attends === null) {
                throw MemberNotFoundException::create();
            }

            if ($attends === false) {
                throw MemberDoesNotAttendAppointmentsException::create();
            }

            $schedule = StaffSchedule::create(
                $this->schedules->nextId(),
                $this->businessContext->current(),
                $member,
            );

            foreach ($command->slots as $slot) {
                $schedule->addSlot(Weekday::from($slot->weekday), TimeRange::create($slot->opensAt, $slot->closesAt));
            }

            $this->schedules->replace($schedule);

            return $schedule;
        });
    }
}
