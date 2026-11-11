<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Colones;

/** What the panel sends to register a service; the business is never part of it. */
final readonly class CreateServiceCommand
{
    /** Receive the values already validated by the request. */
    public function __construct(
        public string $name,
        public int $durationMinutes,
        public Colones $price,
        public int $bufferMinutes,
        public int $maxPerfumes,
        public bool $isActive = true,
    ) {}
}
