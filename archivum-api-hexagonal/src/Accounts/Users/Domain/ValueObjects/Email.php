<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Email
{
    public const int MAX_LENGTH = 255;

    public string $value;

    /** Trim and lower-case the address, then reject malformed or oversized values. */
    public function __construct(string $value)
    {
        $value = mb_strtolower(trim($value));

        if (mb_strlen($value) > self::MAX_LENGTH || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidValueException('accounts.email_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
