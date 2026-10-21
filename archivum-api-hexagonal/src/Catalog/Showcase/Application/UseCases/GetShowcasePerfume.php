<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\UseCases;

use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseDetail;
use Src\Catalog\Showcase\Application\Exceptions\ShowcasePerfumeNotFoundException;
use Src\Shared\Domain\Contracts\FileStorage;

final readonly class GetShowcasePerfume
{
    /** Receive the reader, which only reaches the business of the address, and the file storage. */
    public function __construct(
        private ShowcaseReader $reader,
        private FileStorage $storage,
    ) {}

    /**
     * Show a perfume of the showcase of the business of the address; one that the showcase does not list is not found.
     *
     * @throws ShowcasePerfumeNotFoundException
     */
    public function execute(string $perfumeId): ShowcaseDetail
    {
        $perfume = $this->reader->find($perfumeId) ?? throw ShowcasePerfumeNotFoundException::create();

        return $perfume->withPhotoUrl($perfume->photoPath === null ? null : $this->storage->url($perfume->photoPath));
    }
}
