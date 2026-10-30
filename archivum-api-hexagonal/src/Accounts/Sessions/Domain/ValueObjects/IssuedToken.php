<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\ValueObjects;

use DateTimeImmutable;
use SensitiveParameter;

final readonly class IssuedToken
{
    /** Hold a token just issued, in plain text because it is shown only once, and the instant it stops working. */
    public function __construct(
        #[SensitiveParameter] public string $plainTextToken,
        public DateTimeImmutable $expiresAt,
    ) {}
}
