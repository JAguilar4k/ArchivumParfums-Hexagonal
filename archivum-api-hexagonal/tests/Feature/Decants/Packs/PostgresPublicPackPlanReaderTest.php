<?php

declare(strict_types=1);

use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Packs\Application\DTOs\PackPlanOnSale;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresPublicPackPlanReader;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('reads only the offers on sale of the business of the context, whatever the database is scoped to', function () {
    $mine = BusinessModel::factory()->create();
    $other = BusinessModel::factory()->create();
    $onSale = DecantPackPlanModel::factory()->create(['business_id' => $mine->id]);
    DecantPackPlanModel::factory()->inactive()->create(['business_id' => $mine->id, 'name' => 'Fuera de venta']);
    DecantPackPlanModel::factory()->create(['business_id' => $other->id, 'name' => 'De la otra tienda']);
    $reader = new PostgresPublicPackPlanReader(new FixedBusinessContext(new BusinessId($mine->id)));

    // Scoped to the other business, only the filter of the reader keeps the offers of that business away.
    $this->scopeQueriesTo($other->id);

    expect($reader->onSale())->toBe([]);

    // Scoped to the right business, as the address of the store scopes a request, it reads the offer on sale.
    $this->scopeQueriesTo($mine->id);

    expect($reader->onSale())->toEqual([new PackPlanOnSale($onSale->id, 'Descubrimiento', 5, 5, 40000, 180, 2500)]);
});
