<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\ValueObjects;

use SensitiveParameter;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PlainPassword
{
    public const int MIN_LENGTH = 8;

    public string $value;

    /** Reject passwords shorter than the minimum and keep the text exactly as typed. */
    public function __construct(#[SensitiveParameter] string $value)
    {
        if (mb_strlen($value) < self::MIN_LENGTH) {
            throw new InvalidValueException('accounts.password_too_short', ['min' => self::MIN_LENGTH]);
        }

        $this->value = $value;
    }
}
