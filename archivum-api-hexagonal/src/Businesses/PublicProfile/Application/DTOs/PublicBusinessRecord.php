<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Application\DTOs;

final readonly class PublicBusinessRecord
{
    /** The public columns of a business as they are stored: the logo is a path, not yet an address. */
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $logoPath,
        public ?string $appointmentPlace,
        public string $timezone,
    ) {}
}
