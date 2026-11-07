<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Src\Businesses\Members\Application\Contracts\MemberDirectory;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class InMemoryMemberDirectory implements MemberDirectory
{
    /** Read the members of the in-memory repository. */
    public function __construct(private InMemoryMemberRepository $members) {}

    /**
     * List the staff of a business.
     *
     * @return list<MemberProfile>
     */
    public function all(BusinessId $businessId): array
    {
        $staff = array_filter($this->members->all(), fn (Member $member): bool => $member->businessId->equals($businessId));

        return array_values(array_map($this->toProfile(...), $staff));
    }

    /** Find one person of the staff of a business. */
    public function find(MemberId $id, BusinessId $businessId): ?MemberProfile
    {
        $member = $this->members->find($id, $businessId);

        return $member === null ? null : $this->toProfile($member);
    }

    /** Map a member to what the panel shows, with a fictitious name and email. */
    private function toProfile(Member $member): MemberProfile
    {
        return new MemberProfile(
            $member->id->value,
            'Persona',
            "{$member->id->value}@personal.test",
            $member->role,
            $member->attendsAppointments,
            $member->isActive,
        );
    }
}
