<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain;

use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class Business
{
    private function __construct(
        public BusinessId $id,
        public BusinessName $name,
        public BusinessSlug $slug,
        public BusinessTimezone $timezone,
        public AppointmentPlace $appointmentPlace,
        public BusinessSettings $settings,
    ) {}

    /** Register a new business with the default settings. */
    public static function create(
        BusinessId $id,
        BusinessName $name,
        BusinessSlug $slug,
        BusinessTimezone $timezone,
        AppointmentPlace $appointmentPlace,
    ): self {
        return new self($id, $name, $slug, $timezone, $appointmentPlace, BusinessSettings::defaults());
    }

    /** Rebuild a business already stored, without applying creation defaults. */
    public static function reconstitute(
        BusinessId $id,
        BusinessName $name,
        BusinessSlug $slug,
        BusinessTimezone $timezone,
        AppointmentPlace $appointmentPlace,
        BusinessSettings $settings,
    ): self {
        return new self($id, $name, $slug, $timezone, $appointmentPlace, $settings);
    }
}
