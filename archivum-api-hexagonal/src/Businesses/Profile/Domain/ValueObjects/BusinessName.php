<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessName
{
    public const int MAX_LENGTH = 100;

    public string $value;

    /** Trim the name and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('businesses.name_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
