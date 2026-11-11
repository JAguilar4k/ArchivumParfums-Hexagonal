<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Repositories;

use Src\Decants\Packs\Application\Contracts\PublicPackPlanReader;
use Src\Decants\Packs\Application\DTOs\PackPlanOnSale;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Read the offers on sale straight from their table, without the domain, like the showcase. */
final readonly class PostgresPublicPackPlanReader implements PublicPackPlanReader
{
    /** Receive the business of the address, which every query filters by besides row-level security. */
    public function __construct(private BusinessContext $context) {}

    public function onSale(): array
    {
        return array_values(DecantPackPlanModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (DecantPackPlanModel $row): PackPlanOnSale => new PackPlanOnSale(
                $row->id, $row->name, $row->decants_count, $row->ml_per_decant, $row->price, $row->validity_days, $row->max_price_per_ml,
            ))
            ->all());
    }
}
