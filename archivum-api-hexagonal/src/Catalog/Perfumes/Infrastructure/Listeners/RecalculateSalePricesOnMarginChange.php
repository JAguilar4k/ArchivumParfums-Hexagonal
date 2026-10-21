<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Listeners;

use Src\Businesses\Profile\Domain\Events\BusinessMarginChanged;
use Src\Catalog\Perfumes\Application\UseCases\RecalculateSalePrices;

/** Listen to the Businesses module: when the margin changes, the prices without a manual price follow it in the same transaction. */
final readonly class RecalculateSalePricesOnMarginChange
{
    public function __construct(private RecalculateSalePrices $recalculate) {}

    public function handle(BusinessMarginChanged $event): void
    {
        $this->recalculate->execute($event->businessId, $event->marginColones);
    }
}
