<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

use DateTimeImmutable;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Application\DTOs\SoldPackPage;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;

interface DecantPackReader
{
    /**
     * The packs the account bought in the business of the operation, newest first. Each one has the status
     * DecantPack::statusAt gives it at that moment: one whose payment deadline or validity passed is already expired.
     *
     * @return list<PurchasedPackView>
     */
    public function ofAccount(string $userId, DateTimeImmutable $at): array;

    /**
     * A page of the packs sold in the business of the operation, newest first, each with its status at that moment;
     * with a status, only the packs that have it at that moment.
     */
    public function sold(?DecantPackStatus $status, DateTimeImmutable $at, int $page, int $perPage): SoldPackPage;
}
