<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Perfumes\Application\Contracts\BottleMargin;
use Src\Shared\Domain\ValueObjects\Colones;

/** A margin per bottle the test chose; the default is the one every business starts with. */
final readonly class FixedBottleMargin implements BottleMargin
{
    public function __construct(private int $margin = 5000) {}

    public function current(): Colones
    {
        return new Colones($this->margin);
    }
}
