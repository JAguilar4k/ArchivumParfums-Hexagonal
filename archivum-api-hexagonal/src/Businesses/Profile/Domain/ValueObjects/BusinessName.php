<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessName
{
    public const int MAX_LENGTH = 100;

    public string $value;

    /** Reject empty names and names longer than the maximum. */
    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidValueException('businesses.name_empty');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidValueException('businesses.name_too_long', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $trimmed;
    }
}
