<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Accounts;

use SensitiveParameter;
use Src\Accounts\Sessions\Application\UseCases\EndAllSessions;
use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Application\UseCases\RegisterAccount;
use Src\Accounts\Users\Application\UseCases\RenameAccount;
use Src\Businesses\Members\Application\Contracts\StaffAccounts;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class AccountsStaffAccounts implements StaffAccounts
{
    /** Receive the use cases of Accounts that open and rename an account and close its sessions. */
    public function __construct(
        private RegisterAccount $registerAccount,
        private RenameAccount $renameAccount,
        private EndAllSessions $endAllSessions,
    ) {}

    /** Open the account through Accounts. */
    public function open(string $name, string $email, #[SensitiveParameter] string $password): UserId
    {
        return $this->registerAccount->execute(new RegisterAccountCommand($name, $email, $password))->id;
    }

    /** Rename the account through Accounts. */
    public function rename(UserId $userId, string $name): void
    {
        $this->renameAccount->execute($userId->value, $name);
    }

    /** Close the sessions through Accounts. */
    public function revokeSessions(UserId $userId): void
    {
        $this->endAllSessions->execute($userId->value);
    }
}
