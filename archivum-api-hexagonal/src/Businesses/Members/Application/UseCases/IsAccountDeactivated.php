<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\Contracts\DeactivatedAccounts;

final readonly class IsAccountDeactivated
{
    /** Receive the port that knows the deactivated people. */
    public function __construct(private DeactivatedAccounts $accounts) {}

    /** Whether an email, as a person types it, belongs to someone deactivated from a staff. */
    public function execute(string $email): bool
    {
        return $this->accounts->includes(mb_strtolower(trim($email)));
    }
}
