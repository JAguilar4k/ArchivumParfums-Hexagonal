<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\DTOs;

use SensitiveParameter;

final readonly class RegisterBusinessCommand
{
    /** Carry the data needed to register a business and its owner. */
    public function __construct(
        public string $name,
        public string $slug,
        public string $ownerName,
        public string $ownerEmail,
        #[SensitiveParameter] public string $ownerPassword,
        public string $timezone = 'America/Costa_Rica',
        public string $appointmentPlace = '',
        public bool $ownerAttendsAppointments = false,
    ) {}
}
