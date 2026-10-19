<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use DateTimeZone;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessTimezone
{
    public const string DEFAULT = 'America/Costa_Rica';

    /** Accept only IANA identifiers such as America/Costa_Rica. */
    public function __construct(public string $value)
    {
        if (! in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidValueException('businesses.timezone_invalid');
        }
    }
}
