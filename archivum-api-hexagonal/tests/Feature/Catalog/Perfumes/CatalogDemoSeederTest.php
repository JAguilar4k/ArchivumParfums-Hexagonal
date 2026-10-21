<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\CatalogDemoSeeder;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeNoteModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeTagModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function () {
    seed(BusinessesSeeder::class);
    seed(CatalogDemoSeeder::class);
});

it('seeds sample perfumes with notes, tags and presentations with a manual price in each demo business', function (string $businessId, int $perfumes) {
    $this->scopeQueriesTo($businessId);

    expect(PerfumeModel::query()->count())->toBe($perfumes)
        ->and(PerfumeNoteModel::query()->count())->toBeGreaterThan($perfumes)
        ->and(PerfumeTagModel::query()->count())->toBeGreaterThan($perfumes)
        ->and(PerfumeVariantModel::query()->count())->toBeGreaterThanOrEqual($perfumes)
        ->and(PerfumeVariantModel::query()->whereNull('sale_price_override')->count())->toBe(0)
        ->and(PerfumeModel::query()->where('is_active', false)->count())->toBe(0);
})->with([
    'the pilot business' => [BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, 4],
    'the second business' => [BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID, 3],
]);

it('gives each business its own perfumes and none of the other', function () {
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);
    $pilot = PerfumeModel::query()->pluck('name')->all();

    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID);
    $second = PerfumeModel::query()->pluck('name')->all();

    expect($pilot)->toContain('Oud Nocturno')
        ->and($second)->toContain('Cedro y Bruma')
        ->and(array_intersect($pilot, $second))->toBe([]);
});

it('does not repeat the perfumes when it runs again', function () {
    seed(CatalogDemoSeeder::class);

    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);

    expect(PerfumeModel::query()->count())->toBe(4);
});
