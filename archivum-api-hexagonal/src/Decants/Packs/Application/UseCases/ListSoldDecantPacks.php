<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\DecantPackReader;
use Src\Decants\Packs\Application\DTOs\SoldPackPage;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;

final readonly class ListSoldDecantPacks
{
    /** Receive the reader, which only reaches the business of the session. */
    public function __construct(private DecantPackReader $reader) {}

    /**
     * List a page of the packs sold in the business of the session, newest first, with their status at that moment;
     * with a status of the public API, such as vencido, only the packs that have it then.
     */
    public function execute(?string $status, string $at, int $page = 1, int $perPage = 20): SoldPackPage
    {
        return $this->reader->sold($status === null ? null : DecantPackStatus::fromSpanish($status), new DateTimeImmutable($at), $page, $perPage);
    }
}
