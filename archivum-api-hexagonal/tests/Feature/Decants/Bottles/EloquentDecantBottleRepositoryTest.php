<?php

declare(strict_types=1);

use Src\Decants\Bottles\Domain\ValueObjects\DecantBottleId;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Repositories\EloquentDecantBottleRepository;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('reconstitutes a bottle and filters by context independently from the PostgreSQL scope', function () {
    $first = DecantBottleModel::factory()->withRemainingMl(75)->create(['reserved_ml' => 5]);
    $second = DecantBottleModel::factory()->create();
    $this->scopeQueriesTo($first->business_id);
    $ownRepository = new EloquentDecantBottleRepository(new FixedBusinessContext(new BusinessId($first->business_id)));
    $foreignRepository = new EloquentDecantBottleRepository(new FixedBusinessContext(new BusinessId($second->business_id)));

    $bottle = $ownRepository->find(new DecantBottleId($first->id));

    expect($bottle?->availableMl())->toBe(70);
    expect($bottle?->perfumeVariantId)->toBe($first->perfume_variant_id);
    expect($foreignRepository->find(new DecantBottleId($first->id)))->toBeNull();
});
