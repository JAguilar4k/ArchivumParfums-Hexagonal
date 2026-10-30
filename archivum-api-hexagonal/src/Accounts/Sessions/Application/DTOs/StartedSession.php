<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\DTOs;

final readonly class StartedSession
{
    /** Carry the plain text token, shown only once, and when it expires in ISO 8601 with its offset. */
    public function __construct(
        public string $plainTextToken,
        public string $expiresAt,
    ) {}
}
