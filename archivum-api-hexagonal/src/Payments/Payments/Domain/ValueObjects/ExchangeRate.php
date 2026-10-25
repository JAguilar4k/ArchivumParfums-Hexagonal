<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ExchangeRate
{
    public int $scaled;

    /** Store positive colones per dollar with up to six decimal places, without binary money arithmetic. */
    public function __construct(public string $value)
    {
        if (preg_match('/^(\d{1,9})(?:\.(\d{1,6}))?$/D', $value, $matches) !== 1) {
            throw new InvalidValueException('payments.invalid_exchange_rate');
        }
        $scaled = ((int) $matches[1] * 1_000_000) + (int) str_pad($matches[2] ?? '', 6, '0');
        if ($scaled <= 0) {
            throw new InvalidValueException('payments.invalid_exchange_rate');
        }
        $this->scaled = $scaled;
    }
}
