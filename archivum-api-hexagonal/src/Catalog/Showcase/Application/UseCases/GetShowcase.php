<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\UseCases;

use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseItem;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;
use Src\Shared\Domain\Contracts\FileStorage;

final readonly class GetShowcase
{
    /** Receive the reader, which only reaches the business of the address, and the file storage. */
    public function __construct(
        private ShowcaseReader $reader,
        private FileStorage $storage,
    ) {}

    /** Show one page of the showcase of the business of the address, with the address of each photo. */
    public function execute(ShowcaseFilters $filters): ShowcasePage
    {
        $page = $this->reader->page($filters);

        return new ShowcasePage(
            array_map(
                fn (ShowcaseItem $item): ShowcaseItem => $item->withPhotoUrl($item->photoPath === null ? null : $this->storage->url($item->photoPath)),
                $page->items,
            ),
            $page->total,
            $page->page,
            $page->perPage,
        );
    }
}
