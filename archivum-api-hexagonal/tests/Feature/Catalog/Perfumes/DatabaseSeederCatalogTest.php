<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('seeds the sample catalog of each demo business with the default seeder, the one db:seed runs', function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);

    seed();

    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);
    expect(PerfumeModel::query()->count())->toBe(4);

    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID);
    expect(PerfumeModel::query()->count())->toBe(3);
});
