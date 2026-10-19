<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Contracts;

use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AlreadyAMemberException;
use Src\Shared\Domain\ValueObjects\MemberId;

interface MemberRepository
{
    /** Generate the identity of a new member. */
    public function nextId(): MemberId;

    /**
     * Persist a new or modified member.
     *
     * @throws AlreadyAMemberException
     */
    public function save(Member $member): void;
}
