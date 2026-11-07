<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\Contracts;

interface DeactivatedAccounts
{
    /** Whether the normalised email belongs to a deactivated person of a staff. */
    public function includes(string $email): bool;
}
