<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain\Entities;

use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final class Service
{
    private function __construct(
        public readonly ServiceId $id,
        public readonly BusinessId $businessId,
        public readonly string $name,
        public readonly int $durationMinutes,
        public readonly Colones $price,
        public readonly int $bufferMinutes,
        public readonly int $maxPerfumes,
        private bool $isActive
    ) {
        $this->ensureInvariants();
    }

    public static function create(
        ServiceId $id,
        BusinessId $businessId,
        string $name,
        int $durationMinutes,
        Colones $price,
        int $bufferMinutes,
        int $maxPerfumes
    ): self {
        return new self(
            $id, $businessId, $name, $durationMinutes, $price, $bufferMinutes, $maxPerfumes, true
        );
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

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
