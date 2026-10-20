<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\Contracts;

use Src\Catalog\Perfumes\Application\DTOs\PerfumePage;

interface PerfumeSearch
{
    /**
     * Find one page of the perfumes of the current business, active or not, ordered by brand and name.
     * The text is looked for in the brand and the name, ignoring case; the search and the paging happen in the database.
     */
    public function search(?string $text, int $page, int $perPage): PerfumePage;
}
