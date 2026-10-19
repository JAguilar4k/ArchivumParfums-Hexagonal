<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\DTOs;

use SensitiveParameter;

final readonly class StartSessionCommand
{
    /** Carry the credentials and the name of the device that signs in. */
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $password,
        public string $deviceName,
    ) {}
}
