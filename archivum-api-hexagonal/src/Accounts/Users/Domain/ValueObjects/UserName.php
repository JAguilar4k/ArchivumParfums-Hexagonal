<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class UserName
{
    public const int MAX_LENGTH = 120;

    public string $value;

    /** Trim the name and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('accounts.name_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
