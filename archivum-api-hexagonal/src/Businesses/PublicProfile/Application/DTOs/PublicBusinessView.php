<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Application\DTOs;

final readonly class PublicBusinessView
{
    /** What a visitor may know about a business; it carries no settings, no staff and no internal identifier. */
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $logoUrl,
        public ?string $appointmentPlace,
        public string $timezone,
    ) {}
}
