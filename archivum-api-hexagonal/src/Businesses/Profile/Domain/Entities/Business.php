<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\Entities;

use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\ValueObjects\BusinessId;

final class Business
{
    /** Hold the full state of a business. */
    private function __construct(
        public readonly BusinessId $id,
        public private(set) BusinessName $name,
        public readonly BusinessSlug $slug,
        public private(set) BusinessTimezone $timezone,
        public private(set) ?AppointmentPlace $appointmentPlace,
        public private(set) BusinessSettings $settings,
    ) {}

    /** Register a new business with the default settings, with or without an appointment place. */
    public static function create(
        BusinessId $id,
        BusinessName $name,
        BusinessSlug $slug,
        BusinessTimezone $timezone,
        ?AppointmentPlace $appointmentPlace,
    ): self {
        return new self($id, $name, $slug, $timezone, $appointmentPlace, BusinessSettings::defaults());
    }

    /** Rebuild a business already stored, without applying creation defaults. */
    public static function reconstitute(
        BusinessId $id,
        BusinessName $name,
        BusinessSlug $slug,
        BusinessTimezone $timezone,
        ?AppointmentPlace $appointmentPlace,
        BusinessSettings $settings,
    ): self {
        return new self($id, $name, $slug, $timezone, $appointmentPlace, $settings);
    }

    /** Change the profile and the settings; the slug stays, so the public addresses of the business keep working. */
    public function revise(
        BusinessName $name,
        BusinessTimezone $timezone,
        ?AppointmentPlace $appointmentPlace,
        BusinessSettings $settings,
    ): void {
        $this->name = $name;
        $this->timezone = $timezone;
        $this->appointmentPlace = $appointmentPlace;
        $this->settings = $settings;
    }
}
