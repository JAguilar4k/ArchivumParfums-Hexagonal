<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Infrastructure\Decants\BottleDecantStock;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\HoldReservationForCompetingRequest;

pest()->use(RefreshDatabaseAsOwner::class);

it('R25: accepts one of two simultaneous HTTP carts for the last ml and leaves no orphan order or line', function () {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $bottleId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $plainToken = 'fictitious-concurrency-token-'.Str::uuid7();
    $slug = 'concurrent-order-'.Str::uuid7();

    $tokenId = $owner->transaction(function () use ($owner, $businessId, $userId, $perfumeId, $variantId, $bottleId, $customerId, $plainToken, $slug): int {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 15000, ...$timestamps]);
        $owner->table('decant_bottles')->insert(['id' => $bottleId, 'business_id' => $businessId, 'perfume_variant_id' => $variantId, 'total_ml' => 100, 'remaining_ml' => 5, 'reserved_ml' => 0, 'status' => 'open', 'opened_at' => now(), ...$timestamps]);

        return (int) $owner->table('personal_access_tokens')->insertGetId(['tokenable_type' => UserModel::class, 'tokenable_id' => $userId, 'name' => 'concurrency-test', 'token' => hash('sha256', $plainToken), 'abilities' => '["*"]', ...$timestamps]);
    });
    $token = $tokenId.'|'.$plainToken;

    $request = function () use ($slug, $bottleId, $token): array {
        app()->singleton(HoldReservationForCompetingRequest::class, fn (): HoldReservationForCompetingRequest => new HoldReservationForCompetingRequest(app(BottleDecantStock::class)));
        app()->bind(DecantStock::class, fn (): HoldReservationForCompetingRequest => app(HoldReservationForCompetingRequest::class));
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/tiendas/'.$slug.'/pedidos', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['lineas' => [['botella_id' => $bottleId, 'ml' => 5]]], JSON_THROW_ON_ERROR));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $stock = app(HoldReservationForCompetingRequest::class);

        return ['status' => $response->getStatusCode(), 'overlap' => $stock->observedOverlap];
    };

    $results = inParallel(['orders', 'order_lines', 'decant_movements', 'decant_bottles', 'perfume_variants', 'perfumes', 'customers', 'personal_access_tokens', 'users', 'businesses'], $request, $request);

    expect(array_column($results, 'status'))->toEqualCanonicalizing([201, 409]);
    expect(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false]);
    $owner->transaction(function () use ($owner, $businessId, $bottleId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('orders')->count())->toBe(1);
        expect($owner->table('order_lines')->count())->toBe(1);
        expect($owner->table('decant_movements')->count())->toBe(1);
        expect($owner->table('decant_bottles')->where('id', $bottleId)->value('reserved_ml'))->toBe(5);
        expect($owner->table('decant_bottles')->where('id', $bottleId)->value('remaining_ml'))->toBe(5);
    });
});
