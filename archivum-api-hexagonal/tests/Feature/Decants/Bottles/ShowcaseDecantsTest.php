<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('offers only decant sizes fitting available ml and rounds their server prices', function () {
    $business = actingAsOwner();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[75, 85000, 85000]]);
    $bottle = DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[0]->id->value, 75)->withRemainingMl(7)->create(['reserved_ml' => 2]);

    $response = getJson('/api/v1/tiendas/'.$business->business->slug.'/perfumes/'.$perfume->id->value);
    $response->assertOk()->assertJsonCount(2, 'data.decants')->assertJsonPath('data.decants.0', ['botella_id' => $bottle->id, 'ml' => 2, 'precio' => 2300])->assertJsonPath('data.decants.1.precio', 5700);
});

it('omits empty fully reserved unpriced inactive and foreign bottles', function () {
    $business = actingAsOwner();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 10000, 10000], [75, null, null], [50, 5000, 5000]], inactiveSizes: [50]);
    foreach ([[0, 0], [10, 10], [1, 0]] as [$remaining, $reserved]) {
        DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[2]->id->value, 100)->withRemainingMl($remaining)->create(['reserved_ml' => $reserved]);
    }
    DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[1]->id->value, 75)->create();
    DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[0]->id->value, 50)->create();
    DecantBottleModel::factory()->create();

    getJson('/api/v1/tiendas/'.$business->business->slug.'/perfumes/'.$perfume->id->value)->assertOk()->assertJsonPath('data.decants', []);
});

it('uses the business decant sizes and rounding configuration', function () {
    $business = actingAsOwner();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[75, 85000, 85000]]);
    DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[0]->id->value, 75)->create();
    DB::table('businesses')->where('id', $business->business->id)->update(['settings' => json_encode(['decant_sizes_ml' => [2], 'decant_rounding_colones' => 500], JSON_THROW_ON_ERROR)]);

    getJson('/api/v1/tiendas/'.$business->business->slug.'/perfumes/'.$perfume->id->value)->assertOk()->assertJsonCount(1, 'data.decants')->assertJsonPath('data.decants.0.precio', 2500);
});
