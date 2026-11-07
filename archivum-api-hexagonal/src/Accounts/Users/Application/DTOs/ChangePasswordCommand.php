<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\DTOs;

use SensitiveParameter;

final readonly class ChangePasswordCommand
{
    /** Carry the account, the token of the request, which stays open, and the two passwords. */
    public function __construct(
        public string $userId,
        public string $currentTokenId,
        #[SensitiveParameter] public string $currentPassword,
        #[SensitiveParameter] public string $newPassword,
    ) {}
}
