<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\ValueObjects;

use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class OrderAmounts
{
    /** Store colones only; balance is the part payable after the initial payment. */
    public function __construct(public int $subtotal, public int $dueNow, public int $paid, public int $balance)
    {
        foreach ([$subtotal, $dueNow, $paid, $balance] as $amount) {
            new Colones($amount);
        }
    }

    /**
     * Apply the deposit once to the total of the bottles, rounding up to the next colon.
     *
     * @param  list<OrderLine>  $lines
     */
    public static function calculate(array $lines, int $paid = 0, int $depositPercent = 50): self
    {
        if ($depositPercent < 0 || $depositPercent > 100) {
            throw new InvalidValueException('orders.invalid_deposit');
        }

        $bottles = 0;
        $decants = 0;

        foreach ($lines as $line) {
            if ($line->status === OrderLineStatus::Unavailable || $line->decantPackId !== null) {
                continue;
            }

            if ($line->kind === OrderLineKind::Bottle) {
                $bottles += $line->total();
            } else {
                $decants += $line->total();
            }
        }

        $subtotal = $bottles + $decants;
        $dueNow = intdiv($bottles * $depositPercent + 99, 100) + $decants;

        return new self($subtotal, $dueNow, $paid, max(0, $subtotal - max($dueNow, $paid)));
    }
}
