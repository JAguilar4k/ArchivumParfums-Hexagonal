<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Contracts;

use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AlreadyAMemberException;
use Src\Businesses\Members\Domain\ValueObjects\Membership;
use Src\Shared\Domain\ValueObjects\UserId;

interface MemberRepository
{
    /**
     * Persist a new or modified member.
     *
     * @throws AlreadyAMemberException
     */
    public function save(Member $member): void;

    /** Find the business and role of an account; an account belongs to the staff of one business at most. */
    public function findByUser(UserId $userId): ?Membership;
}
