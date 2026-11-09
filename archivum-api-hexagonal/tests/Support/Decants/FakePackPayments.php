<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Application\Contracts\PackPayments;
use Src\Decants\Packs\Application\DTOs\PackPayment;

/** Payments of packs that the test records: one pending attempt per pack, as StartPayment keeps it. */
final class FakePackPayments implements PackPayments
{
    /** @var list<array{pack: string, user: string, method: string}> the payments started, in order */
    public private(set) array $started = [];

    /** @var array<string, PackPayment> the attempt of each pack */
    private array $attempts = [];

    public function start(string $packId, string $userId, string $method): PackPayment
    {
        $this->started[] = ['pack' => $packId, 'user' => $userId, 'method' => $method];

        return $this->attempts[$packId] ??= new PackPayment('0192f0c4-0000-7000-9000-90000000'.substr($packId, -4), 40000, 'CRC', 40000, 'onvo', 'pending', 'https://checkout.onvopay.com/pay/'.$packId, $method, false);
    }

    public function last(string $packId): ?PackPayment
    {
        return $this->attempts[$packId] ?? null;
    }
}
