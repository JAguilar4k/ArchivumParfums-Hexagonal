<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

interface DecantSizes
{
    /**
     * The decant sizes, in ml, that the business of the session sells (R4).
     *
     * @return list<int>
     */
    public function current(): array;
}
