<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\Contracts;

use SensitiveParameter;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Shared\Domain\ValueObjects\UserId;

interface StaffAccounts
{
    /**
     * Open the account of a new person of the staff.
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function open(string $name, string $email, #[SensitiveParameter] string $password): UserId;

    /** Change the name of the account of a person of the staff. */
    public function rename(UserId $userId, string $name): void;

    /** Close every session of the account, so an old token does not keep abilities it should not have. */
    public function revokeSessions(UserId $userId): void;
}
