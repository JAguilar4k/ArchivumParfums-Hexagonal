<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\DecantPackReader;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;

final readonly class ListMyDecantPacks
{
    /** Receive the reader, which only reaches the business of the operation. */
    public function __construct(private DecantPackReader $reader) {}

    /**
     * List the packs the account bought in the business of the operation, newest first, with their decants left and
     * their status at that moment: a pack whose validity ended shows expired even before the queue expires it (R35).
     *
     * @return list<PurchasedPackView>
     */
    public function execute(string $userId, string $at): array
    {
        return $this->reader->ofAccount($userId, new DateTimeImmutable($at));
    }
}
