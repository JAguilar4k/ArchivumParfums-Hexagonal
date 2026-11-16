<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Sales\Orders\Domain\Entities\OrderLine;

interface DecantPackRedemption
{
    /**
     * Pay a decant line of an order with the pack it names, before the order is saved: take one decant of that pack
     * of the customer, which stays locked until the transaction of the order ends (R34). A pack that is not the
     * customer's, or a decant the pack does not cover, rejects the line at the index the cart sent it; a pack that
     * cannot be used now is a conflict (R35).
     */
    public function redeem(OrderLine $line, string $customerId, int $salePrice, int $presentationMl, DateTimeImmutable $at, int $lineIndex): void;

    /** Keep, once the order is saved, the pack each line redeemed its decant from, so the decant can go back to it. */
    public function record(OrderLine $line): void;

    /** Give back to its pack the decant a line of a cancelled or expired order redeemed (precisions of R32–R35). */
    public function giveBack(OrderLine $line, DateTimeImmutable $at): void;
}
