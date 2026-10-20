<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\Contracts\PerfumeSearch;
use Src\Catalog\Perfumes\Application\DTOs\ListPerfumesQuery;
use Src\Catalog\Perfumes\Application\DTOs\PerfumePage;

final readonly class ListPerfumes
{
    /** Receive the search port, which only reaches the business of the session. */
    public function __construct(private PerfumeSearch $search) {}

    /** List one page of the perfumes of the business of the session, active and inactive. */
    public function execute(ListPerfumesQuery $query): PerfumePage
    {
        $text = $query->text === null ? null : trim($query->text);

        return $this->search->search($text === '' ? null : $text, max(1, $query->page), max(1, $query->perPage));
    }
}
