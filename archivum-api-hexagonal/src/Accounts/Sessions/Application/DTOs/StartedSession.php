<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\DTOs;

final readonly class StartedSession
{
    /** Carry the plain text token, shown only once. */
    public function __construct(public string $plainTextToken) {}
}
