<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\Contracts\MemberDirectory;
use Src\Businesses\Members\Application\Contracts\StaffAccounts;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Application\DTOs\UpdateMemberCommand;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\LastActiveOwnerException;
use Src\Businesses\Members\Domain\Exceptions\MemberNotFoundException;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class UpdateMember
{
    /** Receive the member repository, the directory, the accounts of the staff, the audit trail and the transaction. */
    public function __construct(
        private MemberRepository $members,
        private MemberDirectory $directory,
        private StaffAccounts $accounts,
        private AuditTrail $audit,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Change a person of the staff. A change of role or a deactivation closes their sessions, because a token keeps
     * the abilities it was issued with, and the business always keeps one active owner. Every change and the entry of
     * the audit trail are saved together or not at all.
     *
     * @throws MemberNotFoundException
     * @throws LastActiveOwnerException
     */
    public function execute(UpdateMemberCommand $command): MemberProfile
    {
        $id = new MemberId($command->memberId);
        $businessId = new BusinessId($command->businessId);

        return $this->transaction->run(function () use ($command, $id, $businessId): MemberProfile {
            $member = $this->members->find($id, $businessId) ?? throw MemberNotFoundException::create();
            $updated = $member->update($command->role, $command->attendsAppointments, $command->isActive);

            if ($member->isActiveOwner() && ! $updated->isActiveOwner() && $this->members->countActiveOwners($businessId) <= 1) {
                throw LastActiveOwnerException::create();
            }

            $this->members->save($updated);
            $this->audit($member, $updated);

            if ($command->name !== null) {
                $this->accounts->rename($member->userId, $command->name);
            }

            if ($updated->role !== $member->role || ($member->isActive && ! $updated->isActive)) {
                $this->accounts->revokeSessions($member->userId);
            }

            return $this->directory->find($id, $businessId) ?? throw MemberNotFoundException::create();
        });
    }

    /** Leave one entry for a change of role or of state, whatever else the same request changed. */
    private function audit(Member $member, Member $updated): void
    {
        $action = match (true) {
            $member->isActive && ! $updated->isActive => AuditAction::MemberDeactivated,
            ! $member->isActive && $updated->isActive => AuditAction::MemberActivated,
            $member->role !== $updated->role => AuditAction::MemberRoleChanged,
            default => null,
        };

        if ($action !== null) {
            $this->audit->record(
                $member->businessId,
                $action,
                'member',
                $member->id->value,
                ['role' => $member->role->value, 'is_active' => $member->isActive],
                ['role' => $updated->role->value, 'is_active' => $updated->isActive],
            );
        }
    }
}
