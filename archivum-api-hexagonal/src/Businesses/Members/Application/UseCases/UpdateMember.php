<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\Contracts\MemberDirectory;
use Src\Businesses\Members\Application\Contracts\StaffAccounts;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Application\DTOs\UpdateMemberCommand;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Exceptions\LastActiveOwnerException;
use Src\Businesses\Members\Domain\Exceptions\MemberNotFoundException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class UpdateMember
{
    /** Receive the member repository, the directory and the accounts of the staff. */
    public function __construct(
        private MemberRepository $members,
        private MemberDirectory $directory,
        private StaffAccounts $accounts,
    ) {}

    /**
     * Change a person of the staff. A change of role or a deactivation closes their sessions, because a token keeps
     * the abilities it was issued with, and the business always keeps one active owner.
     *
     * @throws MemberNotFoundException
     * @throws LastActiveOwnerException
     */
    public function execute(UpdateMemberCommand $command): MemberProfile
    {
        $id = new MemberId($command->memberId);
        $businessId = new BusinessId($command->businessId);
        $member = $this->members->find($id, $businessId) ?? throw MemberNotFoundException::create();
        $updated = $member->update($command->role, $command->attendsAppointments, $command->isActive);

        if ($member->isActiveOwner() && ! $updated->isActiveOwner() && $this->members->countActiveOwners($businessId) <= 1) {
            throw LastActiveOwnerException::create();
        }

        $this->members->save($updated);

        if ($command->name !== null) {
            $this->accounts->rename($member->userId, $command->name);
        }

        if ($updated->role !== $member->role || ($member->isActive && ! $updated->isActive)) {
            $this->accounts->revokeSessions($member->userId);
        }

        return $this->directory->find($id, $businessId) ?? throw MemberNotFoundException::create();
    }
}
