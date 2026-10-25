<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\MovementPage;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;

final readonly class ListDecantMovements
{
    public function __construct(private BottleReader $reader) {}

    public function execute(string $id, int $page = 1, int $perPage = 20): MovementPage
    {
        $this->reader->find($id) ?? throw BottleNotFoundException::create();

        return $this->reader->movements($id, $page, $perPage);
    }
}
