<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

use Src\Scheduling\Appointments\Application\DTOs\StaffAgendaFilter;
use Src\Scheduling\Appointments\Application\DTOs\StaffAppointmentView;

/**
 * Reader of the agenda of the staff of the store of the context (#137). It reads its own projection with the customer,
 * the service, the person and the perfumes loaded ahead, so the listing never walks the aggregate nor asks for each
 * name in its own query (#141). Every query is limited to the business pinned in the transaction.
 */
interface StaffAgenda
{
    /**
     * The appointments of the store that start inside the window, in the order the staff reads them.
     *
     * @return list<StaffAppointmentView>
     */
    public function of(StaffAgendaFilter $filter): array;

    /** One appointment of the store of the context by its identity; one of another store is null. */
    public function viewOf(string $id): ?StaffAppointmentView;
}
