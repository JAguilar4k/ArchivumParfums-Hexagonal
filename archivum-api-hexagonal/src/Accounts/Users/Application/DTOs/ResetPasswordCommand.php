<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\DTOs;

use SensitiveParameter;

final readonly class ResetPasswordCommand
{
    /** Carry the email, the token of the link and the new password. */
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $token,
        #[SensitiveParameter] public string $newPassword,
    ) {}
}
