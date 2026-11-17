<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Colones;

/** One service as the public storefront shows it: the fields the visitor needs and nothing internal, such as the margin. */
final readonly class BookableService
{
    /** Receive the stored values of a service that can be booked. */
    public function __construct(
        public string $id,
        public string $name,
        public int $durationMinutes,
        public Colones $price,
        public int $maxPerfumes,
    ) {}
}
