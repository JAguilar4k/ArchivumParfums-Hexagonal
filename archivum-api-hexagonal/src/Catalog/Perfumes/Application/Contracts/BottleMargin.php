<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\Contracts;

use Src\Shared\Domain\ValueObjects\Colones;

interface BottleMargin
{
    /** The margin per bottle that the business of the session has configured. */
    public function current(): Colones;
}
