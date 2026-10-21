<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain;

use Exception;

final class Service
{
    private function __construct(
        private readonly string $id,
        private string $name,
        private int $duration,
        private int $price,
        private int $margin,
        private int $maxPerfumes,
        private bool $isActive
    ) {}

    public static function create(
        string $id,
        string $name,
        int $duration,
        int $price,
        int $margin,
        int $maxPerfumes
    ): self {
        if ($duration <= 0) {
            throw new Exception('scheduling.service.invalid_duration');
        }
        if ($price < 0) {
            throw new Exception('scheduling.service.invalid_price');
        }
        if ($margin < 0) {
            throw new Exception('scheduling.service.invalid_margin');
        }
        if ($maxPerfumes < 0) {
            throw new Exception('scheduling.service.invalid_max_perfumes');
        }

        return new self($id, $name, $duration, $price, $margin, $maxPerfumes, true);
    }

    public static function reconstitute(
        string $id,
        string $name,
        int $duration,
        int $price,
        int $margin,
        int $maxPerfumes,
        bool $isActive
    ): self {
        return new self($id, $name, $duration, $price, $margin, $maxPerfumes, $isActive);
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function reactivate(): void
    {
        $this->isActive = true;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
