<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\DTOs;

final readonly class OpenBottleCommand
{
    public function __construct(public string $variantId, public ?int $totalMl, public ?int $remainingMl, public string $openedAt, public ?string $notes) {}
}
