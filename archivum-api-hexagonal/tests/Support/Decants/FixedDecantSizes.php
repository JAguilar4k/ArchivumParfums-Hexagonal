<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Application\Contracts\DecantSizes;

/** Decant sizes the test chose; the default ones are those every business starts with (R4). */
final readonly class FixedDecantSizes implements DecantSizes
{
    /** @param  list<int>  $sizes */
    public function __construct(private array $sizes = [2, 5, 10]) {}

    public function current(): array
    {
        return $this->sizes;
    }
}
