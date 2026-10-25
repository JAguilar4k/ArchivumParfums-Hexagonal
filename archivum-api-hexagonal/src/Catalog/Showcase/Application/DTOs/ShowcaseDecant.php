<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

final readonly class ShowcaseDecant
{
    public function __construct(public string $bottleId, public int $ml, public int $price) {}
}
