<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Src\Catalog\Perfumes\Application\Contracts\PerfumePhotos;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class EloquentPerfumePhotos implements PerfumePhotos
{
    /** Take the business that every query of the adapter filters by. */
    public function __construct(private BusinessContext $context) {}

    public function pathOf(PerfumeId $perfume): ?string
    {
        return $this->perfumes($perfume)->value('image_path');
    }

    public function save(PerfumeId $perfume, string $path): void
    {
        $this->perfumes($perfume)->update(['image_path' => $path]);
    }

    /** @return Builder<PerfumeModel> */
    private function perfumes(PerfumeId $perfume): Builder
    {
        return PerfumeModel::query()
            ->where('business_id', $this->context->current()->value)
            ->whereKey($perfume->value);
    }
}
