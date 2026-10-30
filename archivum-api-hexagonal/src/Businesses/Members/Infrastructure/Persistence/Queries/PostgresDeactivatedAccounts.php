<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Businesses\Members\Application\Contracts\DeactivatedAccounts;

final readonly class PostgresDeactivatedAccounts implements DeactivatedAccounts
{
    /** Ask fn_member_deactivated, which reads members before any business is pinned. */
    public function includes(string $email): bool
    {
        return (bool) DB::scalar('select fn_member_deactivated(?)', [$email]);
    }
}
