<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Contracts;

use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AccountAlreadyOnStaffException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

interface MemberRepository
{
    /** Generate the identity of a new member. */
    public function nextId(): MemberId;

    /**
     * Persist a new or modified member.
     *
     * @throws AccountAlreadyOnStaffException
     */
    public function save(Member $member): void;

    /** Find a member of one business. */
    public function find(MemberId $id, BusinessId $businessId): ?Member;

    /** Count the active owners of a business, holding their rows until the transaction ends. */
    public function countActiveOwners(BusinessId $businessId): int;
}
