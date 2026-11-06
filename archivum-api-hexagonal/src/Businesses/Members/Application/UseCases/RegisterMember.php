<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Businesses\Members\Application\Contracts\StaffAccounts;
use Src\Businesses\Members\Application\DTOs\AddMemberCommand;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Application\DTOs\RegisterMemberCommand;

final readonly class RegisterMember
{
    /** Receive the port that opens accounts and the use case that adds an account to a staff. */
    public function __construct(
        private StaffAccounts $accounts,
        private AddMember $addMember,
    ) {}

    /**
     * Open the account of a new person and add it to the staff. An email already registered is refused, whoever
     * owns it: a customer does not become staff by the decision of a third party.
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterMemberCommand $command): MemberProfile
    {
        $userId = $this->accounts->open($command->name, $command->email, $command->password);

        $member = $this->addMember->execute(
            new AddMemberCommand($userId->value, $command->businessId, $command->role, $command->attendsAppointments),
        );

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
