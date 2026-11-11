<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class ActivateDecantPackCommand
{
    /** Carry the pack whose payment was confirmed and the moment its validity starts counting. */
    public function __construct(public string $packId, public string $activatedAt) {}
}
