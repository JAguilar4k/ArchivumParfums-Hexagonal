<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

/**
 * The person of the staff behind the account of the session, in the store of the context. The attendance of a
 * collaborator is checked against this identity and never against one the request sends (#138).
 */
interface AttendanceMembers
{
    /** The member of the store of the context that belongs to the account of the session. */
    public function memberOf(string $userId): string;
}
