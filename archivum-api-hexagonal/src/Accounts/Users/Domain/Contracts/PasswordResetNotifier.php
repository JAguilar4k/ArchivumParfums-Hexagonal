<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Contracts;

use SensitiveParameter;

interface PasswordResetNotifier
{
    /** Send the link that carries the token to the email of the account. */
    public function send(string $email, #[SensitiveParameter] string $token): void;
}
