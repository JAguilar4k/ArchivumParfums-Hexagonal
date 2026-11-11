<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

use Src\Payments\Payments\Domain\Entities\Refund;

final readonly class OrderRefund
{
    /**
     * A refund of an order as the owner sees it: the original payment it returns, its colones, its state and whether it
     * left for the provider without an answer yet, so a person may have to check it there.
     */
    public function __construct(
        public string $id,
        public string $paymentId,
        public ?string $orderLineId,
        public int $amount,
        public string $status,
        public bool $awaitsAnswer,
    ) {}

    public static function from(Refund $refund): self
    {
        return new self($refund->id, $refund->paymentId, $refund->orderLineId, $refund->amount, $refund->status,
            $refund->status === 'pending' && $refund->attempted && $refund->providerReference === null);
    }
}
