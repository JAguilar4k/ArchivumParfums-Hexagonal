<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\DTOs\AddMemberCommand;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AccountAlreadyOnStaffException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class AddMember
{
    /** Receive the member repository port. */
    public function __construct(private MemberRepository $members) {}

    /**
     * Link an account to the staff of one business.
     *
     * @throws AccountAlreadyOnStaffException
     */
    public function execute(AddMemberCommand $command): Member
    {
        $member = Member::create(
            $this->members->nextId(),
            new UserId($command->userId),
            new BusinessId($command->businessId),
            $command->role,
            $command->attendsAppointments,
        );

        $this->members->save($member);

        return $member;
    }
}
