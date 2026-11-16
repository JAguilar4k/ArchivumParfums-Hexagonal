<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

/**
 * The person of the staff behind the account of the session, in the store of the context. The agenda of a collaborator
 * is narrowed with this identity and never with one the request sends (#137).
 */
interface StaffAgendaMembers
{
    /** The member of the staff of the store of the context that belongs to the account of the session. */
    public function memberOf(string $userId): string;
}
