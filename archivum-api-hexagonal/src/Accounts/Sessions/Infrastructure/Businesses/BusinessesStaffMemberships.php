<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Businesses;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Businesses\Members\Application\UseCases\FindMemberByAccount;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Profile\Application\UseCases\FindBusiness;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class BusinessesStaffMemberships implements StaffMemberships
{
    /** Receive the use cases of Businesses that find a membership and its business. */
    public function __construct(
        private FindMemberByAccount $findMember,
        private FindBusiness $findBusiness,
    ) {}

    /** Ask Members for the role of the account and Profile for the name and slug of its business. */
    public function of(UserId $userId): ?StaffMembership
    {
        $member = $this->findMember->execute($userId->value);
        $business = $member === null ? null : $this->findBusiness->execute($member->businessId);

        if ($member === null || $business === null) {
            return null;
        }

        return new StaffMembership(
            $member->role === MemberRole::Owner ? AccountRole::Owner : AccountRole::Staff,
            $business->id->value,
            $business->name->value,
            $business->slug->value,
        );
    }
}
