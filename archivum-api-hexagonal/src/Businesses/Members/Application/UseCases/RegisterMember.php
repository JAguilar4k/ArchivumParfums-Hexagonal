<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Businesses\Members\Application\Contracts\StaffAccounts;
use Src\Businesses\Members\Application\DTOs\AddMemberCommand;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Application\DTOs\RegisterMemberCommand;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\AuditAction;

final readonly class RegisterMember
{
    /** Receive the port that opens accounts, the use case that adds an account to a staff, the audit trail and the transaction. */
    public function __construct(
        private StaffAccounts $accounts,
        private AddMember $addMember,
        private AuditTrail $audit,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Open the account of a new person and add it to the staff. An email already registered is refused, whoever
     * owns it: a customer does not become staff by the decision of a third party. The account, the membership and the
     * entry of the audit trail are saved together or not at all.
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterMemberCommand $command): MemberProfile
    {
        $member = $this->transaction->run(function () use ($command): Member {
            $userId = $this->accounts->open($command->name, $command->email, $command->password);

            $member = $this->addMember->execute(
                new AddMemberCommand($userId->value, $command->businessId, $command->role, $command->attendsAppointments),
            );

            $this->audit->record($member->businessId, AuditAction::MemberAdded, 'member', $member->id->value, null, [
                'user_id' => $member->userId->value,
                'role' => $member->role->value,
                'attends_appointments' => $member->attendsAppointments,
                'is_active' => $member->isActive,
            ]);

            return $member;
        });

        return new MemberProfile(
            $member->id->value,
            $command->name,
            mb_strtolower(trim($command->email)),
            $member->role,
            $member->attendsAppointments,
            $member->isActive,
        );
    }
}
