<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Businesses\Members\Application\Contracts\Memberships;
use Src\Businesses\Members\Application\DTOs\Membership;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class PostgresMemberships implements Memberships
{
    /**
     * Ask fn_member_business, which reads members before any business is pinned: the policy of members only shows
     * the rows of the business of the transaction, and the business of a person is what this lookup finds.
     */
    public function of(UserId $userId): ?Membership
    {
        $row = DB::selectOne('select business_id, role from fn_member_business(?)', [$userId->value]);

        return $row === null ? null : new Membership($row->business_id, MemberRole::from($row->role));
    }
}
