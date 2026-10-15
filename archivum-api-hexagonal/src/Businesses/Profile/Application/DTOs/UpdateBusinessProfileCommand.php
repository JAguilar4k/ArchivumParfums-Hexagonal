<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\DTOs;

final readonly class UpdateBusinessProfileCommand
{
    /**
     * Carry the fields to change; absent keys keep their current value. The settings go by the property names of
     * BusinessSettings and are combined with the stored ones.
     *
     * @param array{
     *     name?: string,
     *     appointment_place?: string,
     *     timezone?: string,
     *     settings?: array<string, int|float|string|list<int>|null>,
     * } $changes
     */
    public function __construct(
        public string $businessId,
        public array $changes,
    ) {}
}
