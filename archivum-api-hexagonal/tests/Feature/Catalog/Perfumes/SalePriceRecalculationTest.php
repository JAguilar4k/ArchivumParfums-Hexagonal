<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Catalog\Perfumes\Domain\Contracts\VariantCost;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\FixedVariantCost;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const PROFILE_URL = '/api/v1/panel/tienda';

/**
 * Sale prices of the presentations of a business by milliliters and perfume name, with the database scoped to it.
 *
 * @return array<string, int|null>
 */
function salePricesOf(string $businessId): array
{
    app(BusinessTransaction::class)->scope($businessId);

    return DB::table('perfume_variants')
        ->join('perfumes', 'perfumes.id', '=', 'perfume_variants.perfume_id')
        ->orderBy('perfumes.name')->orderBy('size_ml')
        ->get(['perfumes.name', 'size_ml', 'sale_price'])
        ->mapWithKeys(fn (object $row): array => ["{$row->name} {$row->size_ml} ml" => $row->sale_price])
        ->all();
}

/**
 * Two businesses with presentations whose prices are stale, as if they were calculated with the margin of 5 000 and a cost of 100 000,
 * a supplier that answers that cost for every presentation, and the owner of the first business signed in.
 *
 * @return array{string, string} The identifier of each business.
 */
function priceScenario(): array
{
    TwoBusinesses::create();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    StoredPerfumes::create($first, name: 'Calculado', variants: [[5, 105000, null], [10, null, null]]);
    StoredPerfumes::create($first, name: 'Manual', variants: [[50, 90000, 90000]]);
    StoredPerfumes::create($second, name: 'Ajeno', variants: [[100, 105000, null]]);
    app()->instance(VariantCost::class, new FixedVariantCost(default: 100000));
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    return [$first, $second];
}

it('recalculates the sale price of the presentations without a manual price when the margin changes', function () {
    [$first, $second] = priceScenario();
    patchJson(PROFILE_URL, ['ajustes' => ['margen_botella' => 7000]])->assertOk();

    expect(salePricesOf($first))->toBe([
        'Calculado 5 ml' => 107000,
        'Calculado 10 ml' => 107000,
        'Manual 50 ml' => 90000,
    ]);
});

it('does not change the prices of the other business', function () {
    [$first, $second] = priceScenario();
    patchJson(PROFILE_URL, ['ajustes' => ['margen_botella' => 7000]])->assertOk();

    expect(salePricesOf($second))->toBe(['Ajeno 100 ml' => 105000]);
});

it('leaves the prices alone when the change is not of the margin', function () {
    [$first, $second] = priceScenario();
    patchJson(PROFILE_URL, ['nombre' => 'Perfumería Primera Renovada', 'ajustes' => ['anticipacion_minima_horas' => 6]])->assertOk();

    expect(salePricesOf($first))->toBe([
        'Calculado 5 ml' => 105000,
        'Calculado 10 ml' => null,
        'Manual 50 ml' => 90000,
    ]);
});

it('leaves the prices alone when the margin is sent with the value it already has', function () {
    [$first, $second] = priceScenario();
    patchJson(PROFILE_URL, ['ajustes' => ['margen_botella' => 5000]])->assertOk();

    expect(salePricesOf($first)['Calculado 10 ml'])->toBeNull();
});

it('undoes the change of the margin when the recalculation fails, because both happen in one transaction', function () {
    [$first, $second] = priceScenario();
    app()->instance(VariantCost::class, new class implements VariantCost
    {
        public function costOf(PerfumeVariantId $variant): never
        {
            throw new RuntimeException('The supplier is not answering.');
        }
    });

    patchJson(PROFILE_URL, ['ajustes' => ['margen_botella' => 7000]])->assertStatus(500);

    patchJson(PROFILE_URL, ['ajustes' => ['anticipacion_minima_horas' => 6]])->assertOk()
        ->assertJsonPath('data.ajustes.margen_botella', 5000);
    expect(salePricesOf($first)['Calculado 5 ml'])->toBe(105000);
});

it('prices a presentation made through the API with the cost plus the margin and goes back to it when the manual price is removed', function () {
    [$first, $second] = priceScenario();
    $perfume = StoredPerfumes::create($first, name: 'Nuevo');
    $url = "/api/v1/panel/perfumes/{$perfume->id->value}/presentaciones";

    $created = postJson($url, ['ml' => 50])->assertCreated()->assertJsonPath('data.precio_venta', 105000)->assertJsonPath('data.precio_manual', null);
    $id = (string) $created->json('data.id');

    patchJson("{$url}/{$id}", ['precio_manual' => 98000])->assertOk()->assertJsonPath('data.precio_venta', 98000);
    $this->scopeQueriesTo($first);
    expect(PerfumeVariantModel::query()->findOrFail($id)->sale_price)->toBe(98000);

    patchJson("{$url}/{$id}", ['precio_manual' => null])->assertOk()->assertJsonPath('data.precio_venta', 105000);
});

it('leaves the sale price empty for a presentation without a cost and without a manual price', function () {
    [$first, $second] = priceScenario();
    app()->instance(VariantCost::class, new FixedVariantCost);
    $perfume = StoredPerfumes::create($first, name: 'Sin costo');

    postJson("/api/v1/panel/perfumes/{$perfume->id->value}/presentaciones", ['ml' => 50])
        ->assertCreated()
        ->assertJsonPath('data.precio_venta', null);
});
