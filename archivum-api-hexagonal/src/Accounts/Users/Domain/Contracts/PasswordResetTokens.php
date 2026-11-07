<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Contracts;

use SensitiveParameter;
use Src\Shared\Domain\ValueObjects\UserId;

interface PasswordResetTokens
{
    /** Create the single-use token of an account, or answer null when one was created a moment ago. */
    public function issue(UserId $userId): ?string;

    /** Tell whether the token is the current one of the account and has not expired. */
    public function isValid(UserId $userId, #[SensitiveParameter] string $token): bool;

    /** Delete the token of the account, so it cannot be used again. */
    public function revoke(UserId $userId): void;
}
