<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Businesses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Appointments\Application\Contracts\StaffAgendaMembers;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class BusinessesStaffAgendaMembers implements StaffAgendaMembers
{
    /** Receive the business pinned for the request. */
    public function __construct(private BusinessContext $business) {}

    /**
     * Read the person of the staff behind the account, among the members of the pinned business. An account that is not
     * a member of the store is forbidden, as Sanctum answers for a token without the ability of the route.
     */
    public function memberOf(string $userId): string
    {
        $member = DB::table('members')
            ->where('members.business_id', $this->business->current()->value)
            ->where('members.user_id', $userId)
            ->value('members.id');

        if (! is_string($member)) {
            throw new AuthorizationException;
        }

        return $member;
    }
}
