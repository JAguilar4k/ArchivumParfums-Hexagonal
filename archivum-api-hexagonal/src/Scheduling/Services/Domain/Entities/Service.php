<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain\Entities;

use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

/**
 * A service the business offers to book: how long it lasts, what it costs, the margin kept before the next
 * appointment and the most perfumes a customer may try. It never decides whether a slot is free: the availability
 * of #61 calculates that and the exclusion constraint of #59 guarantees it.
 */
final class Service
{
    /** Build a service the business of the session just created; it starts active. */
    private function __construct(
        public readonly ServiceId $id,
        public readonly BusinessId $businessId,
        public string $name,
        public int $durationMinutes,
        public Colones $price,
        public int $bufferMinutes,
        public int $maxPerfumes,
        private bool $isActive,
    ) {
        $this->ensureInvariants();
    }

    /** Register a new service, active from the first moment. */
    public static function create(
        ServiceId $id,
        BusinessId $businessId,
        string $name,
        int $durationMinutes,
        Colones $price,
        int $bufferMinutes,
        int $maxPerfumes,
    ): self {
        return new self($id, $businessId, $name, $durationMinutes, $price, $bufferMinutes, $maxPerfumes, true);
    }

    /** Rebuild a service from its stored state, without resetting its state. */
    public static function reconstitute(
        ServiceId $id,
        BusinessId $businessId,
        string $name,
        int $durationMinutes,
        Colones $price,
        int $bufferMinutes,
        int $maxPerfumes,
        bool $isActive,
    ): self {
        return new self($id, $businessId, $name, $durationMinutes, $price, $bufferMinutes, $maxPerfumes, $isActive);
    }

    /** Change the name the customer reads in the storefront. */
    public function rename(string $name): void
    {
        $this->name = $name;
        $this->ensureInvariants();
    }

    /** Change how long the appointment lasts; the appointments already booked keep their own end (#53). */
    public function changeDuration(int $durationMinutes): void
    {
        $this->durationMinutes = $durationMinutes;
        $this->ensureInvariants();
    }

    /** Change the price of the appointment. */
    public function changePrice(Colones $price): void
    {
        $this->price = $price;
    }

    /** Change the margin kept after the appointment. */
    public function changeBuffer(int $bufferMinutes): void
    {
        $this->bufferMinutes = $bufferMinutes;
        $this->ensureInvariants();
    }

    /** Change the most perfumes a customer may try in this service. */
    public function changeMaxPerfumes(int $maxPerfumes): void
    {
        $this->maxPerfumes = $maxPerfumes;
        $this->ensureInvariants();
    }

    /** Offer the service again in the storefront. */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /** Stop offering the service without deleting it, so its history stays. */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /** Tell whether the storefront offers the service. */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** Keep the invariants the table also enforces with its CHECK constraints. */
    private function ensureInvariants(): void
    {
        if (trim($this->name) === '') {
            throw new InvalidValueException('scheduling.service.name_required');
        }

        if ($this->durationMinutes <= 0) {
            throw new InvalidValueException('scheduling.service.invalid_duration');
        }

        if ($this->bufferMinutes < 0) {
            throw new InvalidValueException('scheduling.service.invalid_buffer');
        }

        if ($this->maxPerfumes < 0) {
            throw new InvalidValueException('scheduling.service.invalid_max_perfumes');
        }
    }
}
