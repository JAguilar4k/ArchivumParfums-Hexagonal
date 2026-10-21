<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\Contracts;

use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;

interface PerfumePhotos
{
    /** The path of the photo a perfume of the current business has now, or null when it has none. */
    public function pathOf(PerfumeId $perfume): ?string;

    /** Keep the path of the new photo of a perfume of the current business. */
    public function save(PerfumeId $perfume, string $path): void;
}
