<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessSlug
{
    private const string PATTERN = '/^[a-z0-9-]+$/';

    /** Accept only lowercase letters, digits and hyphens. */
    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidValueException('businesses.slug_invalid');
        }
    }
}
