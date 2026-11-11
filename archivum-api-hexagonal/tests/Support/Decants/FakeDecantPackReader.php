<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\DecantPackReader;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Application\DTOs\SoldPackPage;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;

/** A reader of packs that answers what the test gives it and keeps what each reading asked for. */
final class FakeDecantPackReader implements DecantPackReader
{
    /** @var list<array{string, DateTimeImmutable}> */
    public private(set) array $accounts = [];

    /** @var list<array{?DecantPackStatus, DateTimeImmutable, int, int}> */
    public private(set) array $pages = [];

    /** @param  list<PurchasedPackView>  $bought */
    public function __construct(private readonly array $bought = [], private readonly ?SoldPackPage $sold = null) {}

    public function ofAccount(string $userId, DateTimeImmutable $at): array
    {
        $this->accounts[] = [$userId, $at];

        return $this->bought;
    }

    public function sold(?DecantPackStatus $status, DateTimeImmutable $at, int $page, int $perPage): SoldPackPage
    {
        $this->pages[] = [$status, $at, $page, $perPage];

        return $this->sold ?? new SoldPackPage([], 0, $page, $perPage);
    }
}
