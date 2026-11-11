<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Decants\Packs\Domain\Contracts\DecantPackBalance;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresDecantPackBalance;
use Tests\Support\Decants\HoldRedemptionForCompetingOrder;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('R34: redeems the last decant of a pack once when two HTTP orders ask for it at once, and leaves nothing of the other', function () {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $bottleId = (string) Str::uuid7();
    $planId = (string) Str::uuid7();
    $packId = (string) Str::uuid7();
    $plainToken = 'fictitious-redemption-token-'.Str::uuid7();
    $slug = 'concurrent-redemption-'.Str::uuid7();

    $tokenId = $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $perfumeId, $variantId, $bottleId, $planId, $packId, $plainToken, $slug): int {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 120000, ...$timestamps]);
        $owner->table('decant_bottles')->insert(['id' => $bottleId, 'business_id' => $businessId, 'perfume_variant_id' => $variantId, 'total_ml' => 100, 'remaining_ml' => 100, 'reserved_ml' => 0, 'status' => 'open', 'opened_at' => now(), ...$timestamps]);
        $owner->table('decant_pack_plans')->insert(['id' => $planId, 'business_id' => $businessId, 'name' => 'Descubrimiento', 'decants_count' => 5, 'ml_per_decant' => 5,
            'price' => 40000, 'validity_days' => 180, 'max_price_per_ml' => 2500, ...$timestamps]);
        $owner->table('decant_packs')->insert(['id' => $packId, 'business_id' => $businessId, 'customer_id' => $customerId, 'decant_pack_plan_id' => $planId, 'total_decants' => 5,
            'remaining_decants' => 1, 'ml_per_decant' => 5, 'max_price_per_ml' => 2500, 'status' => 'active', 'expires_at' => now()->addDays(180), ...$timestamps]);

        return (int) $owner->table('personal_access_tokens')->insertGetId(['tokenable_type' => UserModel::class, 'tokenable_id' => $userId, 'name' => 'concurrency-test', 'token' => hash('sha256', $plainToken), 'abilities' => '["*"]', ...$timestamps]);
    });
    $token = $tokenId.'|'.$plainToken;

    // Both orders pay a decant of the bottle with the last decant of the pack.
    $request = function () use ($slug, $bottleId, $packId, $token): array {
        app()->singleton(HoldRedemptionForCompetingOrder::class, fn (): HoldRedemptionForCompetingOrder => new HoldRedemptionForCompetingOrder(app(PostgresDecantPackBalance::class)));
        app()->bind(DecantPackBalance::class, fn (): HoldRedemptionForCompetingOrder => app(HoldRedemptionForCompetingOrder::class));
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/tiendas/'.$slug.'/pedidos', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['lineas' => [['botella_id' => $bottleId, 'ml' => 5, 'paquete_id' => $packId]]], JSON_THROW_ON_ERROR));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $problem = json_decode((string) $response->getContent(), true);

        return ['status' => $response->getStatusCode(), 'detail' => is_array($problem) ? ($problem['detail'] ?? null) : null,
            'overlap' => app(HoldRedemptionForCompetingOrder::class)->observedOverlap];
    };

    $results = inParallel(['decant_pack_redemptions', 'order_lines', 'orders', 'decant_movements', 'decant_bottles', 'decant_packs', 'decant_pack_plans',
        'perfume_variants', 'perfumes', 'customers', 'personal_access_tokens', 'users', 'businesses'], $request, $request);
    usort($results, fn (array $a, array $b): int => $a['status'] <=> $b['status']);

    expect(array_column($results, 'status'))->toBe([201, 409])
        ->and($results[1]['detail'])->toBe('El paquete ya no tiene decants disponibles.')
        // The order that lost read the pack with its last decant and waited on the UPDATE of the other: the race was real.
        ->and(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false]);
    $owner->transaction(function () use ($owner, $businessId, $bottleId, $packId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('orders')->count())->toBe(1)
            ->and($owner->table('order_lines')->count())->toBe(1)
            ->and($owner->table('decant_pack_redemptions')->count())->toBe(1)
            ->and($owner->table('decant_movements')->count())->toBe(1)
            ->and((array) $owner->table('decant_packs')->where('id', $packId)->first(['remaining_decants', 'status']))->toBe(['remaining_decants' => 0, 'status' => 'exhausted'])
            ->and((array) $owner->table('decant_bottles')->where('id', $bottleId)->first(['remaining_ml', 'reserved_ml']))->toBe(['remaining_ml' => 100, 'reserved_ml' => 5]);
    });
});
