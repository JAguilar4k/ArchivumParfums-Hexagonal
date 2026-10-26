<?php

declare(strict_types=1);

use Database\Seeders\BottlesDemoSeeder;
use Database\Seeders\BusinessesSeeder;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('seeds open bottles in both businesses through db seed and offers their decants without repeating stock', function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);
    seed();

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);
        $ownerId = MemberModel::query()->where('business_id', $businessId)->where('role', 'owner')->value('user_id');
        Sanctum::actingAs(UserModel::query()->where('id', $ownerId)->sole(), ['*']);
        $business = BusinessModel::query()->findOrFail($businessId);
        $response = getJson('/api/v1/panel/botellas')->assertOk();
        $first = $response->json('data.0');
        expect($first)->toBeArray();
        getJson('/api/v1/tiendas/'.$business->slug.'/perfumes/'.$first['perfume']['id'])->assertOk()->assertJsonFragment(['botella_id' => $first['id']]);
        $this->scopeQueriesTo($businessId);
        $count = DecantBottleModel::query()->count();
        expect($count)->toBeGreaterThan(0);
        expect(array_values(DecantBottleModel::query()->pluck('business_id')->unique()->all()))->toBe([$businessId]);
        seed(BottlesDemoSeeder::class);
        $this->scopeQueriesTo($businessId);
        expect(DecantBottleModel::query()->count())->toBe($count);
    }
});
