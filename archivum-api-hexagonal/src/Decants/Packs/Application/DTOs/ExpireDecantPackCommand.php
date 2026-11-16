<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class ExpireDecantPackCommand
{
    /**
     * Carry the pack whose deadline arrived, the reason of its expiry and the moment it is checked.
     *
     * @param  string  $reason  unpaid or validity_ended
     */
    public function __construct(
        public string $packId,
        public string $reason,
        public string $checkedAt,
    ) {}
}
