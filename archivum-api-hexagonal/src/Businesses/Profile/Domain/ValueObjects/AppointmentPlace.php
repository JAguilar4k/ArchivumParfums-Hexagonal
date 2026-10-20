<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class AppointmentPlace
{
    public const int MAX_LENGTH = 255;

    public string $value;

    /** Trim the address or meeting point and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('businesses.appointment_place_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
