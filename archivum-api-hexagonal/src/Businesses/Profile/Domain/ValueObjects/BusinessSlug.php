<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessSlug
{
    public const int MAX_LENGTH = 100;

    private const string PATTERN = '/^[a-z0-9-]+$/';

    /** Accept only lowercase letters, digits and hyphens, up to the length of the column. */
    public function __construct(public string $value)
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidValueException('businesses.slug_invalid', ['max' => self::MAX_LENGTH]);
        }
    }
}
