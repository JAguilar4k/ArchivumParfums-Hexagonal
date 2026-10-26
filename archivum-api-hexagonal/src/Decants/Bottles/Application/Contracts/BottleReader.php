<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\Contracts;

use Src\Decants\Bottles\Application\DTOs\BottlePage;
use Src\Decants\Bottles\Application\DTOs\BottleView;
use Src\Decants\Bottles\Application\DTOs\MovementPage;
use Src\Decants\Bottles\Application\DTOs\MovementView;

interface BottleReader
{
    public function page(int $page, int $perPage): BottlePage;

    public function find(string $id): ?BottleView;

    public function presentationMl(string $variantId): ?int;

    public function movements(string $bottleId, int $page, int $perPage): MovementPage;

    public function movement(string $id): MovementView;
}
