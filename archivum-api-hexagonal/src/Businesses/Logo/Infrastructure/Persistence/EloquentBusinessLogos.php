<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Infrastructure\Persistence;

use Src\Businesses\Logo\Application\Contracts\BusinessLogos;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentBusinessLogos implements BusinessLogos
{
    public function pathOf(BusinessId $business): ?string
    {
        return BusinessModel::query()->whereKey($business->value)->value('logo_path');
    }

    public function save(BusinessId $business, string $path): void
    {
        BusinessModel::query()->whereKey($business->value)->update(['logo_path' => $path]);
    }
}
