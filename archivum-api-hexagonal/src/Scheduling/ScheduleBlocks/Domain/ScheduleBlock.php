<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain;

use DateTimeImmutable;
use Exception;
use Src\Scheduling\ScheduleBlocks\Domain\Enums\BlockOrigin;

final class ScheduleBlock
{
    private function __construct(
        private readonly string $id,
        private readonly ?string $memberId, // null si el bloqueo es para toda la tienda
        private readonly DateTimeImmutable $startAt,
        private readonly DateTimeImmutable $endAt,
        private readonly string $reason,
        private readonly BlockOrigin $origin
    ) {}

    public static function create(
        string $id,
        ?string $memberId,
        DateTimeImmutable $startAt,
        DateTimeImmutable $endAt,
        string $reason,
        BlockOrigin $origin
    ): self {
        if ($endAt <= $startAt) {
            throw new Exception('scheduling.schedule_block.invalid_order');
        }

        return new self($id, $memberId, $startAt, $endAt, $reason, $origin);
    }

    public static function reconstitute(
        string $id,
        ?string $memberId,
        DateTimeImmutable $startAt,
        DateTimeImmutable $endAt,
        string $reason,
        BlockOrigin $origin
    ): self {
        return new self($id, $memberId, $startAt, $endAt, $reason, $origin);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMemberId(): ?string
    {
        return $this->memberId;
    }

    public function getStartAt(): DateTimeImmutable
    {
        return $this->startAt;
    }

    public function getEndAt(): DateTimeImmutable
    {
        return $this->endAt;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getOrigin(): BlockOrigin
    {
        return $this->origin;
    }
}
