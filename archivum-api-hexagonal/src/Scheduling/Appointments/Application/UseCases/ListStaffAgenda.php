<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use DateTimeImmutable;
use DateTimeZone;
use Src\Scheduling\Appointments\Application\Contracts\BookingCatalog;
use Src\Scheduling\Appointments\Application\Contracts\StaffAgenda;
use Src\Scheduling\Appointments\Application\Contracts\StaffAgendaMembers;
use Src\Scheduling\Appointments\Application\DTOs\StaffAgendaFilter;
use Src\Scheduling\Appointments\Application\DTOs\StaffAppointmentView;

/**
 * List the agenda of the staff of the store of the context (#137): the appointments that start in the day or the range
 * the panel asks for, read in the time zone of the store. The owner sees every appointment of the store and may narrow
 * it to one person; a collaborator sees only its own, whatever person the request names, because the identity comes
 * from the session and never from the query.
 */
final readonly class ListStaffAgenda
{
    /** Receive the reader of the agenda, the person of the session and the terms of the store. */
    public function __construct(
        private StaffAgenda $appointments,
        private StaffAgendaMembers $members,
        private BookingCatalog $catalog,
    ) {}

    /**
     * The appointments of the window, ordered by their start.
     *
     * @param  bool  $seesEveryPerson  whether the account carries the ability of the owner over the store
     * @param  string|null  $from  first day of the window, `Y-m-d`, or null for today
     * @param  string|null  $to  last day of the window, `Y-m-d`, or null for the same day as $from
     * @param  string|null  $memberId  the person the owner narrows the agenda to, ignored for a collaborator
     * @param  string|null  $status  the state of the appointment, in the language of the domain
     * @return list<StaffAppointmentView>
     */
    public function execute(
        string $userId,
        bool $seesEveryPerson,
        ?string $from = null,
        ?string $to = null,
        ?string $memberId = null,
        ?string $status = null,
    ): array {
        $zone = new DateTimeZone($this->catalog->terms()->timezone);
        $today = (new DateTimeImmutable('now', $zone))->format('Y-m-d');
        $from ??= $today;
        $to ??= $from;

        $startsAt = new DateTimeImmutable($from.' 00:00:00', $zone);
        $endsAt = (new DateTimeImmutable($to.' 00:00:00', $zone))->modify('+1 day');

        return $this->appointments->of(new StaffAgendaFilter(
            $startsAt,
            $endsAt,
            $seesEveryPerson ? $memberId : $this->members->memberOf($userId),
            $status,
        ));
    }
}
