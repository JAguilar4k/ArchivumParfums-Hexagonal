<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Infrastructure\Persistence;

use Src\Businesses\PublicProfile\Application\Contracts\PublicBusinessReader;
use Src\Businesses\PublicProfile\Application\DTOs\PublicBusinessRecord;
use Src\Businesses\PublicProfile\Infrastructure\Persistence\Models\PublicBusinessModel;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentPublicBusinessReader implements PublicBusinessReader
{
    public function find(BusinessId $business): ?PublicBusinessRecord
    {
        $model = PublicBusinessModel::query()->select(PublicBusinessModel::COLUMNS)->find($business->value);

        return $model === null
            ? null
            : new PublicBusinessRecord($model->name, $model->slug, $model->logo_path, $model->location_text, $model->timezone);
    }
}
