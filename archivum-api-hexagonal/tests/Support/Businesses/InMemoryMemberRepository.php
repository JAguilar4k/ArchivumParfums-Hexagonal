<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AccountAlreadyOnStaffException;
use Src\Shared\Domain\ValueObjects\MemberId;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    private array $members = [];

    private int $sequence = 0;

    /** Generate a predictable member identity. */
    public function nextId(): MemberId
    {
        return new MemberId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the member enforcing one business per account. */
    public function save(Member $member): void
    {
        $existing = $this->members[$member->userId->value] ?? null;

        if ($existing !== null && ! $existing->businessId->equals($member->businessId)) {
            throw AccountAlreadyOnStaffException::create();
        }

        $this->members[$member->userId->value] = $member;
    }

    /**
     * List the stored members.
     *
     * @return list<Member>
     */
    public function all(): array
    {
        return array_values($this->members);
    }
}
