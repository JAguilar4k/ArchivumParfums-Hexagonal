<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\BottlePage;

final readonly class ListDecantBottles
{
    public function __construct(private BottleReader $reader) {}

    public function execute(int $page = 1, int $perPage = 20): BottlePage
    {
        return $this->reader->page($page, $perPage);
    }
}
