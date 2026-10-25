<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Payable
{
    /** Identify the resource without importing its domain into Payments. */
    public function __construct(public string $type, public string $id)
    {
        if (! in_array($type, ['order', 'decant_pack'], true)) {
            throw new InvalidValueException('payments.invalid_payable');
        }
    }
}
