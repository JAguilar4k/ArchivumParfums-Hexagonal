<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\DTOs;

use SensitiveParameter;

final readonly class RegisterAccountCommand
{
    /** Carry the data needed to open an account. */
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter] public string $password,
    ) {}
}
