<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\Contracts;

use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;

interface ShowcaseReader
{
    /**
     * One page of the perfumes of the business of the address that can be shown: active, with at least one active presentation
     * that has a sale price, and with only those presentations. Filters, order and paging are resolved in the database.
     */
    public function page(ShowcaseFilters $filters): ShowcasePage;
}
