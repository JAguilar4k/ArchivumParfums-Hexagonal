<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Members;

use SensitiveParameter;
use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Application\UseCases\RegisterAccount;
use Src\Businesses\Members\Application\DTOs\AddMemberCommand;
use Src\Businesses\Members\Application\UseCases\AddMember;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Profile\Application\Contracts\OwnerAccounts;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final readonly class StaffOwnerAccounts implements OwnerAccounts
{
    /** Receive the use cases that open an account and add it to a staff, and the transaction of one business. */
    public function __construct(
        private RegisterAccount $registerAccount,
        private AddMember $addMember,
        private BusinessTransaction $transaction,
    ) {}

    /** Pin the new business in the open transaction, then open the owner account and add it as owner. */
    public function registerOwner(
        BusinessId $businessId,
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
        bool $attendsAppointments,
    ): void {
        $this->transaction->scope($businessId->value);

        $user = $this->registerAccount->execute(new RegisterAccountCommand($name, $email, $password));

        $this->addMember->execute(
            new AddMemberCommand($user->id->value, $businessId->value, MemberRole::Owner, $attendsAppointments),
        );
    }
}
