<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use Src\Decants\Packs\Application\Contracts\PublicPackPlanReader;
use Src\Decants\Packs\Application\DTOs\PackPlanOnSale;

final readonly class ListPackPlansOnSale
{
    /** Receive the reader, which only reaches the business of the address. */
    public function __construct(private PublicPackPlanReader $reader) {}

    /**
     * List the offers that a visitor of the store can buy, by name.
     *
     * @return list<PackPlanOnSale>
     */
    public function execute(): array
    {
        return $this->reader->onSale();
    }
}
