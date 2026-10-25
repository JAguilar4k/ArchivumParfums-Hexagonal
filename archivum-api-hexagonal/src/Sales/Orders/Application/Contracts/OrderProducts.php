<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface OrderProducts
{
    /** @return array{price:int, variant_id:?string} */
    public function quote(string $kind, string $reference, ?int $ml, int $lineIndex): array;

    /** @return array{deposit_percent:int, expiry_minutes:int} */
    public function settings(): array;
}
