<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Colones;

/** What the panel sends to change a service: only the keys present are a change, and the business is not part of it. */
final readonly class EditServiceCommand
{
    /**
     * Receive the service and the changes already validated by the request.
     *
     * @param  array{name?: string, duration_minutes?: int, price?: Colones, buffer_minutes?: int, max_perfumes?: int, active?: bool}  $changes
     */
    public function __construct(
        public string $serviceId,
        public array $changes,
    ) {}
}
