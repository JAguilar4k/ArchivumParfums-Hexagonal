<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

abstract readonly class Identifier
{
    private const string UUID_V7_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /** Accept only canonical lowercase UUIDs of version 7. */
    final public function __construct(public string $value)
    {
        if (preg_match(self::UUID_V7_PATTERN, $value) !== 1) {
            throw new InvalidValueException('shared.invalid_identifier');
        }
    }

    /** Compare two identifiers of the same type by value. */
    final public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }
}
