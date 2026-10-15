<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class AppointmentPlace
{
    public const int MAX_LENGTH = 255;

    public string $value;

    /** Free text with the address or meeting point; an empty text means not configured yet. */
    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidValueException('businesses.place_too_long', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $trimmed;
    }
}
