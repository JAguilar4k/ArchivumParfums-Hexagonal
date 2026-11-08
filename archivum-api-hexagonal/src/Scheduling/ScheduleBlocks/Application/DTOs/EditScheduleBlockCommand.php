<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\DTOs;

use DateTimeImmutable;

/** What the panel sends to change a block: only the keys present are a change, and the business is not part of it. */
final readonly class EditScheduleBlockCommand
{
    /**
     * Receive the block and the changes already validated by the request; the origin is never a change.
     *
     * @param  array{member_id?: string|null, starts_at?: DateTimeImmutable, ends_at?: DateTimeImmutable, reason?: string}  $changes
     */
    public function __construct(
        public string $blockId,
        public array $changes,
    ) {}
}
