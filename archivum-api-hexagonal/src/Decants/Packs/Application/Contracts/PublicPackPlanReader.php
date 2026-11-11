<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

use Src\Decants\Packs\Application\DTOs\PackPlanOnSale;

interface PublicPackPlanReader
{
    /**
     * The offers on sale of the business of the address, by name; the inactive ones are left out.
     *
     * @return list<PackPlanOnSale>
     */
    public function onSale(): array;
}
