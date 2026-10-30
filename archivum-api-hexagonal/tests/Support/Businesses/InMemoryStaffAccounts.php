<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use SensitiveParameter;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Businesses\Members\Application\Contracts\DeactivatedAccounts;
use Src\Businesses\Members\Application\Contracts\StaffAccounts;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryStaffAccounts implements DeactivatedAccounts, StaffAccounts
{
    /** @var array<string, string> */
    public array $renamed = [];

    /** @var list<string> */
    public array $revoked = [];

    private int $sequence = 0;

    /** @param list<string> $deactivatedEmails */
    public function __construct(
        private readonly bool $refuseEmails = false,
        private readonly array $deactivatedEmails = [],
    ) {}

    /** Open an account with a predictable identity, or refuse its email. */
    public function open(string $name, string $email, #[SensitiveParameter] string $password): UserId
    {
        if ($this->refuseEmails) {
            throw EmailAlreadyRegisteredException::create();
        }

        return new UserId(sprintf('0192f0c4-0000-7000-8000-%012d', 100 + ++$this->sequence));
    }

    /** Remember the new name of the account. */
    public function rename(UserId $userId, string $name): void
    {
        $this->renamed[$userId->value] = $name;
    }

    /** Remember that the sessions of the account were closed. */
    public function revokeSessions(UserId $userId): void
    {
        $this->revoked[] = $userId->value;
    }

    /** Whether the email was given as deactivated. */
    public function includes(string $email): bool
    {
        return in_array($email, $this->deactivatedEmails, true);
    }
}
