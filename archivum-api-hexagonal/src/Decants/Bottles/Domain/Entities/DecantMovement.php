<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\Entities;

use DateTimeImmutable;
use Src\Decants\Bottles\Domain\ValueObjects\MovementType;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DecantMovement
{
    public function __construct(
        public MovementType $type,
        public int $ml,
        public ?string $reason,
        public ?string $userId,
        public ?string $orderLineId,
        public DateTimeImmutable $occurredAt,
    ) {
        if ($ml === 0 || ($type !== MovementType::Adjustment && $ml < 0)) {
            throw new InvalidValueException('bottles.invalid_ml');
        }

        if ($type === MovementType::Adjustment && ($reason === null || trim($reason) === '')) {
            throw new InvalidValueException('bottles.reason_required');
        }
    }
}
