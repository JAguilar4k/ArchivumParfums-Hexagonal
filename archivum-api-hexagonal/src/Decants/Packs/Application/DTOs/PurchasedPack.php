<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

use Src\Decants\Packs\Domain\Entities\DecantPack;

/** A purchase and its payment; $created is false when the same purchase came again with its key. */
final readonly class PurchasedPack
{
    public function __construct(
        public DecantPack $pack,
        public ?PackPayment $payment,
        public bool $created,
    ) {}
}
