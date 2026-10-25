<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\BottleView;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;

final readonly class GetDecantBottle
{
    public function __construct(private BottleReader $reader) {}

    public function execute(string $id): BottleView
    {
        return $this->reader->find($id) ?? throw BottleNotFoundException::create();
    }
}
