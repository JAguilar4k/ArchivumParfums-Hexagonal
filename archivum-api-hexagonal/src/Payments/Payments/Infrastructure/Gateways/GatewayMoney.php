<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Gateways;

use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class GatewayMoney
{
    /** Convert the database's whole colones without floating point arithmetic. */
    public static function colonesToMinor(int $amount): int
    {
        if ($amount <= 0 || $amount > 2_147_483_647) {
            throw new InvalidValueException('payments.invalid_amount');
        }

        return $amount * 100;
    }

    /** Convert CRC into USD cents, rounding a half cent up, with a six-decimal rate. */
    public static function dollarsFromColones(int $amount, ?string $exchangeRate): int
    {
        self::colonesToMinor($amount);

        $rate = new ExchangeRate($exchangeRate ?? '')->scaled;

        $numerator = $amount * 100_000_000;
        $cents = intdiv($numerator, $rate);

        if (($numerator % $rate) * 2 >= $rate) {
            $cents++;
        }

        if ($cents <= 0 || $cents > 2_147_483_647) {
            throw new InvalidValueException('payments.invalid_amount');
        }

        return $cents;
    }

    /** Format cents for a provider that requires a JSON decimal string. */
    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Accept only exact two-decimal monetary values; never cast a float to money. */
    public static function cents(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
