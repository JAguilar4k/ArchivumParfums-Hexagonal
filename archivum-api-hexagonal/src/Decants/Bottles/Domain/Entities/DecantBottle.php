<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\Entities;

use DateTimeImmutable;
use Src\Decants\Bottles\Domain\Exceptions\InsufficientMlException;
use Src\Decants\Bottles\Domain\ValueObjects\BottleStatus;
use Src\Decants\Bottles\Domain\ValueObjects\DecantBottleId;
use Src\Decants\Bottles\Domain\ValueObjects\MovementType;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;

final class DecantBottle
{
    private function __construct(
        public readonly DecantBottleId $id,
        public readonly BusinessId $businessId,
        public readonly string $perfumeVariantId,
        public readonly int $totalMl,
        public private(set) int $remainingMl,
        public private(set) int $reservedMl,
        public private(set) BottleStatus $status,
        public readonly DateTimeImmutable $openedAt,
        public private(set) ?string $notes,
    ) {
        if ($totalMl <= 0 || $remainingMl < 0 || $remainingMl > $totalMl || $reservedMl < 0 || $reservedMl > $remainingMl
            || ($status === BottleStatus::Empty) !== ($remainingMl === 0)) {
            throw new InvalidValueException('bottles.invalid_ml');
        }
    }

    public static function create(DecantBottleId $id, BusinessId $businessId, string $perfumeVariantId, int $totalMl, int $remainingMl, DateTimeImmutable $openedAt, ?string $notes): self
    {
        return new self($id, $businessId, $perfumeVariantId, $totalMl, $remainingMl, 0, $remainingMl === 0 ? BottleStatus::Empty : BottleStatus::Open, $openedAt, $notes);
    }

    public static function reconstitute(DecantBottleId $id, BusinessId $businessId, string $perfumeVariantId, int $totalMl, int $remainingMl, int $reservedMl, BottleStatus $status, DateTimeImmutable $openedAt, ?string $notes): self
    {
        return new self($id, $businessId, $perfumeVariantId, $totalMl, $remainingMl, $reservedMl, $status, $openedAt, $notes);
    }

    public function availableMl(): int
    {
        return $this->remainingMl - $this->reservedMl;
    }

    public function updateNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function reserve(int $ml, ?string $userId, ?string $orderLineId, DateTimeImmutable $at): DecantMovement
    {
        $movement = new DecantMovement(MovementType::Reservation, $ml, null, $userId, $orderLineId, $at);

        if ($this->status !== BottleStatus::Open || $ml > $this->availableMl()) {
            throw InsufficientMlException::create();
        }

        $this->reservedMl += $ml;

        return $movement;
    }

    public function release(int $ml, ?string $userId, ?string $orderLineId, DateTimeImmutable $at): DecantMovement
    {
        $movement = new DecantMovement(MovementType::Release, $ml, null, $userId, $orderLineId, $at);

        if ($ml > $this->reservedMl) {
            throw InsufficientMlException::create();
        }

        $this->reservedMl -= $ml;

        return $movement;
    }

    public function consume(int $ml, ?string $userId, ?string $orderLineId, DateTimeImmutable $at): DecantMovement
    {
        $movement = new DecantMovement(MovementType::Consumption, $ml, null, $userId, $orderLineId, $at);

        if ($ml > $this->reservedMl) {
            throw InsufficientMlException::create();
        }

        $this->reservedMl -= $ml;
        $this->remainingMl -= $ml;
        $this->status = $this->remainingMl === 0 ? BottleStatus::Empty : BottleStatus::Open;

        return $movement;
    }

    public function adjust(int $ml, string $reason, string $userId, DateTimeImmutable $at): DecantMovement
    {
        $movement = new DecantMovement(MovementType::Adjustment, $ml, $reason, $userId, null, $at);
        $remaining = $this->remainingMl + $ml;

        if ($this->status !== BottleStatus::Open || $remaining < $this->reservedMl || $remaining > $this->totalMl) {
            throw InsufficientMlException::create();
        }

        $this->remainingMl = $remaining;
        $this->status = $remaining === 0 ? BottleStatus::Empty : BottleStatus::Open;

        return $movement;
    }
}
