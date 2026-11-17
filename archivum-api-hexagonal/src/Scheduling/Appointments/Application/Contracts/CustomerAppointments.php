<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentPeriod;

/**
 * Reader of the appointments of one customer of the store of the context. It reads its own projection instead of
 * walking through the aggregate, and every query is limited to the business pinned in the transaction.
 */
interface CustomerAppointments
{
    /**
     * The appointments of that account in the store, the closest first when they are still ahead and the most
     * recent first when they already happened.
     *
     * @return list<AppointmentView>
     */
    public function of(string $userId, AppointmentPeriod $period): array;

    /** One appointment of that account in the store; one of another customer is null. */
    public function find(string $id, string $userId): ?AppointmentView;
}
