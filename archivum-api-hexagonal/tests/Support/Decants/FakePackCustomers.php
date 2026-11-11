<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Application\Contracts\PackCustomers;

/** Customer records of a store kept in memory: an account gets one the first time it is looked up. */
final class FakePackCustomers implements PackCustomers
{
    /** @var array<string, string> customer of each account */
    public private(set) array $customers = [];

    public function findOrCreate(string $userId): string
    {
        return $this->customers[$userId] ??= sprintf('0192f0c4-0000-7000-9000-%012d', 300 + count($this->customers) + 1);
    }
}
