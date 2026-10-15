<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Contracts;

use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AlreadyAMemberException;

interface MemberRepository
{
    /**
     * Persist a new or modified member.
     *
     * @throws AlreadyAMemberException
     */
    public function save(Member $member): void;
}
