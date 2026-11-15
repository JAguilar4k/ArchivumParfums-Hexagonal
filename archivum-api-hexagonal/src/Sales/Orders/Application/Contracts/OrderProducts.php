<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface OrderProducts
{
    /**
     * Quote a line at the current prices of the business, with the sale price and the ml of its presentation. The ml
     * of a decant must be one of the decant sizes of the business, unless a pack pays it: the pack keeps the ml it was
     * bought with although the business no longer sells that size, and it checks them itself.
     *
     * @return array{price:int, variant_id:?string, sale_price:int, size_ml:int}
     */
    public function quote(string $kind, string $reference, ?int $ml, int $lineIndex, bool $paidWithPack = false): array;

    /** @return array{deposit_percent:int, expiry_minutes:int} */
    public function settings(): array;
}
