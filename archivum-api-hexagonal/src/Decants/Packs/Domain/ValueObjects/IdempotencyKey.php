<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** The key the customer chose for one purchase (#145): the same purchase sent again carries the same key. */
final readonly class IdempotencyKey
{
    public const int MAX_LENGTH = 100;

    /** Keep a key that is not blank and fits its column. */
    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('packs.idempotency_key_invalid', ['max' => self::MAX_LENGTH]);
        }
    }
}
