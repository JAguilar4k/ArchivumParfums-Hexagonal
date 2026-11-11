<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

use Src\Decants\Packs\Application\DTOs\PackPayment;

interface PackPayments
{
    /**
     * Start the payment of the full price of a pack that waits for it, or answer the attempt already pending, for the
     * customer who bought it (R33). The amount is always the price of the stored offer, never one the browser sends.
     *
     * @param  string  $method  card or sinpe_mobile
     */
    public function start(string $packId, string $userId, string $method): PackPayment;

    /** The last payment attempt of a pack, if any: the purchase sent again answers it once the pack no longer waits. */
    public function last(string $packId): ?PackPayment;
}
