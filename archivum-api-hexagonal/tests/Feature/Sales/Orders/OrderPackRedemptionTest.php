<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Sales\Orders\Infrastructure\Jobs\ExpireUnpaidOrderJob;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/**
 * Sign in the customer of a new store that sells a 100 ml presentation from an open bottle, by default at ₡120 000
 * (₡1 200 per ml, under the cap of ₡2 500 of the pack), and give the customer an active pack of 5 decants of 5 ml
 * that is valid for 180 more days.
 *
 * @param  array<string, mixed>  $pack  columns of the pack that change the default ones
 * @return array{business: StaffedBusiness, url: string, variant: string, bottle: string, pack: string}
 */
function storeWithAPack(array $pack = [], int $salePrice = 120000): array
{
    $business = actingAsCustomer();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, $salePrice, $salePrice]]);
    $variant = $perfume->variants[0]->id->value;
    $bottle = DecantBottleModel::factory()->forPresentation($business->business->id, $variant, 100)->create();
    $stored = StoredPacks::create($business, ['status' => 'active', 'expires_at' => now()->addDays(180), ...$pack]);

    return ['business' => $business, 'url' => '/api/v1/tiendas/'.$business->business->slug.'/pedidos', 'variant' => $variant,
        'bottle' => $bottle->id, 'pack' => $stored['pack_id']];
}

/**
 * A decant line of the cart paid with the pack of the store.
 *
 * @param  array{bottle: string, pack: string}  $store
 * @return array{botella_id: string, ml: int, paquete_id: string}
 */
function redeemedLine(array $store, int $ml = 5): array
{
    return ['botella_id' => $store['bottle'], 'ml' => $ml, 'paquete_id' => $store['pack']];
}

/**
 * Nothing of a refused order stays: no order, line or redemption, the decants of the pack and the ml of the bottle
 * are as they were, and the bottle has no movement.
 *
 * @param  array{business: StaffedBusiness, bottle: string, pack: string}  $store
 */
function assertNothingRedeemed(array $store, int $remaining = 5): void
{
    app(BusinessTransaction::class)->scope($store['business']->business->id);
    assertDatabaseCount('orders', 0);
    assertDatabaseCount('order_lines', 0);
    assertDatabaseCount('decant_pack_redemptions', 0);
    assertDatabaseCount('decant_movements', 0);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => $remaining]);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 100, 'reserved_ml' => 0]);
}

/**
 * The history of the bottle as the owner reads it, newest first: the type, the ml and the redemption mark of each
 * movement, by the line of the order that moved it.
 *
 * @return array<string, list<array{string, int, bool}>>
 */
function movementsByLine(string $bottle): array
{
    /** @var list<array{linea_pedido_id: string, tipo: string, ml: int, canjeado: bool}> $movements */
    $movements = getJson('/api/v1/panel/botellas/'.$bottle.'/movimientos')->assertOk()->json('data');
    $byLine = [];
    foreach ($movements as $movement) {
        $byLine[$movement['linea_pedido_id']][] = [$movement['tipo'], $movement['ml'], $movement['canjeado']];
    }

    return $byLine;
}

it('R34: takes a decant of the pack and reserves its ml in the transaction of the order, which does not charge it', function () {
    $store = storeWithAPack();

    $response = postJson($store['url'], ['lineas' => [
        ['presentacion_id' => $store['variant'], 'cantidad' => 1],
        ['botella_id' => $store['bottle'], 'ml' => 5],
        redeemedLine($store),
    ]]);

    // The redeemed decant keeps its price on the line, but only the bottle and the sold decant are charged (R24).
    $response->assertCreated()->assertJsonPath('data.estado', 'pendiente_de_pago')
        ->assertJsonPath('data.subtotal', 126000)
        ->assertJsonPath('data.por_cobrar_ahora', 66000)
        ->assertJsonPath('data.lineas.1.paquete_id', null)
        ->assertJsonPath('data.lineas.2.paquete_id', $store['pack'])
        ->assertJsonPath('data.lineas.2.precio_unitario', 6000)
        ->assertJsonPath('data.lineas.2.estado', 'reservada');
    $sold = $response->json('data.lineas.1.id');
    $redeemed = $response->json('data.lineas.2.id');

    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 4, 'status' => 'active']);
    assertDatabaseHas('decant_pack_redemptions', ['business_id' => $store['business']->business->id, 'decant_pack_id' => $store['pack'], 'order_line_id' => $redeemed]);
    assertDatabaseCount('decant_pack_redemptions', 1);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 100, 'reserved_ml' => 10]);

    actingAsOwner($store['business']);
    expect(movementsByLine($store['bottle']))->toEqual([$sold => [['reserva', 5, false]], $redeemed => [['reserva', 5, true]]]);
});

it('R24: confirms at ₡0 and without the gateway an order whose decants all come from the pack, which runs out', function () {
    $store = storeWithAPack(['remaining_decants' => 2]);

    postJson($store['url'], ['lineas' => [redeemedLine($store), redeemedLine($store)]])->assertCreated()
        ->assertJsonPath('data.estado', 'confirmado')
        ->assertJsonPath('data.subtotal', 0)
        ->assertJsonPath('data.por_cobrar_ahora', 0)
        ->assertJsonPath('data.saldo', 0);

    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 0, 'status' => 'exhausted']);
    assertDatabaseCount('decant_pack_redemptions', 2);
    assertDatabaseCount('payments', 0);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 100, 'reserved_ml' => 10]);
});

it('R26: prepares a redeemed decant like any other, and its consumption shows the redemption (R8)', function () {
    $store = storeWithAPack();
    $order = postJson($store['url'], ['lineas' => [redeemedLine($store)]])->assertCreated();
    actingAsOwner($store['business']);

    postJson('/api/v1/panel/pedidos/'.$order->json('data.id').'/preparacion')->assertOk()
        ->assertJsonPath('data.estado', 'listo')
        ->assertJsonPath('data.lineas.0.estado', 'preparada');

    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 95, 'reserved_ml' => 0]);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 4, 'status' => 'active']);
    expect(movementsByLine($store['bottle']))->toEqual([$order->json('data.lineas.0.id') => [['consumo', 5, true], ['reserva', 5, true]]]);
});

it('redeems a decant of the ml of the pack even after the store stopped selling that size', function () {
    $store = storeWithAPack(['ml_per_decant' => 3]);

    postJson($store['url'], ['lineas' => [['botella_id' => $store['bottle'], 'ml' => 3]]])
        ->assertUnprocessable()->assertJsonPath('errores', ['lineas.0.ml' => [__('orders.decant_size')]]);
    postJson($store['url'], ['lineas' => [redeemedLine($store, 3)]])->assertCreated()
        ->assertJsonPath('data.lineas.0.ml', 3);

    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'reserved_ml' => 3]);
});

it('R34: rejects a decant the pack does not cover at the field of its line, and saves nothing', function (int $ml, int $salePrice, string $field, string $key) {
    $store = storeWithAPack(salePrice: $salePrice);

    postJson($store['url'], ['lineas' => [['presentacion_id' => $store['variant'], 'cantidad' => 1], redeemedLine($store, $ml)]])
        ->assertUnprocessable()->assertJsonPath('errores', ['lineas.1.'.$field => [__($key, ['ml' => 5])]]);

    assertNothingRedeemed($store);
})->with([
    'fewer ml than the pack' => [2, 120000, 'ml', 'packs.ml_mismatch'],
    'more ml than the pack' => [10, 120000, 'ml', 'packs.ml_mismatch'],
    'a presentation above the cap' => [5, 260000, 'botella_id', 'packs.over_price_cap'],
]);

it('rejects a pack that is not one of the customer in this store at the pack of its line, and saves nothing', function (Closure $packOf) {
    $store = storeWithAPack();

    postJson($store['url'], ['lineas' => [['botella_id' => $store['bottle'], 'ml' => 5, 'paquete_id' => $packOf($store)]]])
        ->assertUnprocessable()->assertJsonPath('errores', ['lineas.0.paquete_id' => [__('packs.pack_not_found')]]);

    assertNothingRedeemed($store);
})->with([
    'a pack of another customer of the store' => [function (array $store): string {
        $businessId = $store['business']->business->id;
        $other = app(BusinessTransaction::class)->run($businessId, fn (): string => CustomerModel::factory()->create(['business_id' => $businessId])->id);

        return StoredPacks::create($store['business'], ['customer_id' => $other, 'status' => 'active', 'expires_at' => now()->addDays(180)])['pack_id'];
    }],
    'a pack of another store' => [fn (): string => StoredPacks::create(StaffedBusiness::create(), ['status' => 'active', 'expires_at' => now()->addDays(180)])['pack_id']],
    'a pack that does not exist' => [fn (): string => (string) Str::uuid7()],
]);

it('returns 422 for a pack identifier that is not one, or for a pack on a line of a bottle', function () {
    $store = storeWithAPack();

    postJson($store['url'], ['lineas' => [['botella_id' => $store['bottle'], 'ml' => 5, 'paquete_id' => 'mi-paquete']]])
        ->assertUnprocessable()->assertJsonPath('errores', ['lineas.0.paquete_id' => [__('validation.uuid', ['attribute' => 'paquete'])]]);
    postJson($store['url'], ['lineas' => [['presentacion_id' => $store['variant'], 'cantidad' => 1, 'paquete_id' => $store['pack']]]])
        ->assertUnprocessable()->assertJsonStructure(['errores' => ['lineas.0.paquete_id']]);

    assertNothingRedeemed($store);
});

it('R35: returns 409 for a pack that cannot be used now, and saves nothing', function (string $status, int $remaining, int $minutesLeft, int $lines, string $key) {
    $store = storeWithAPack(['status' => $status, 'remaining_decants' => $remaining, 'expires_at' => now()->addMinutes($minutesLeft)]);

    postJson($store['url'], ['lineas' => array_fill(0, $lines, redeemedLine($store))])
        ->assertConflict()->assertJsonPath('detail', __($key));

    assertNothingRedeemed($store, $remaining);
})->with([
    'waiting for its payment' => ['pending_payment', 5, 30, 1, 'packs.not_active'],
    'expired' => ['expired', 3, -60, 1, 'packs.expired'],
    'active after its validity, before the queue expires it' => ['active', 3, -1, 1, 'packs.expired'],
    'exhausted' => ['exhausted', 0, 1440, 1, 'packs.exhausted'],
    'with fewer decants left than the lines that use it' => ['active', 1, 1440, 2, 'packs.exhausted'],
]);

it('gives the redeemed decants of a cancelled order back to the pack and releases their ml', function () {
    $store = storeWithAPack(['remaining_decants' => 1]);
    $order = postJson($store['url'], ['lineas' => [redeemedLine($store)]])->assertCreated()->json('data.id');

    postJson('/api/v1/cuenta/pedidos/'.$order.'/cancelacion')->assertOk()
        ->assertJsonPath('data.estado', 'cancelado')
        ->assertJsonPath('data.lineas.0.estado', 'liberada');

    // The pack that the redemption had exhausted is active again, because it is still valid.
    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 1, 'status' => 'active']);
    assertDatabaseCount('decant_pack_redemptions', 0);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 100, 'reserved_ml' => 0]);
    assertDatabaseCount('refunds', 0);
});

it('gives the redeemed decants of an order that expired unpaid back to the pack and releases their ml', function () {
    $store = storeWithAPack(['remaining_decants' => 1]);
    // A decant paid with money keeps the order waiting for its payment, besides the one the pack pays.
    $order = postJson($store['url'], ['lineas' => [redeemedLine($store), ['botella_id' => $store['bottle'], 'ml' => 5]]])->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente_de_pago')->json('data.id');
    travel(31)->minutes();

    // The job of its payment deadline, as the worker runs it (#158).
    ExpireUnpaidOrderJob::dispatchSync($order, $store['business']->business->id);

    // The pack that the redemption had exhausted is active again, because it is still valid.
    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('orders', ['id' => $order, 'status' => 'expired']);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 1, 'status' => 'active']);
    assertDatabaseCount('decant_pack_redemptions', 0);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 100, 'reserved_ml' => 0]);
    expect(DB::table('order_lines')->where('order_id', $order)->pluck('status')->all())->toBe(['released', 'released']);
});

it('R35: gives the decant back to a pack whose validity ended meanwhile, which stays expired', function () {
    freezeTime();
    $store = storeWithAPack(['remaining_decants' => 1, 'expires_at' => now()->addDay()]);
    $order = postJson($store['url'], ['lineas' => [redeemedLine($store)]])->assertCreated()->json('data.id');
    travel(2)->days();

    postJson('/api/v1/cuenta/pedidos/'.$order.'/cancelacion')->assertOk()->assertJsonPath('data.estado', 'cancelado');

    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 1, 'status' => 'expired']);
    assertDatabaseCount('decant_pack_redemptions', 0);
});

it('prepares a redeemed decant whose pack expired before it was prepared, and keeps its redemption', function () {
    freezeTime();
    $store = storeWithAPack(['expires_at' => now()->addDay()]);
    $order = postJson($store['url'], ['lineas' => [redeemedLine($store)]])->assertCreated()->json('data.id');
    travel(2)->days();
    $this->scopeQueriesTo($store['business']->business->id);
    DB::table('decant_packs')->where('id', $store['pack'])->update(['status' => 'expired']);
    actingAsOwner($store['business']);

    postJson('/api/v1/panel/pedidos/'.$order.'/preparacion')->assertOk()->assertJsonPath('data.lineas.0.estado', 'preparada');

    $this->scopeQueriesTo($store['business']->business->id);
    assertDatabaseHas('decant_pack_redemptions', ['decant_pack_id' => $store['pack']]);
    assertDatabaseHas('decant_packs', ['id' => $store['pack'], 'remaining_decants' => 4, 'status' => 'expired']);
    assertDatabaseHas('decant_bottles', ['id' => $store['bottle'], 'remaining_ml' => 95, 'reserved_ml' => 0]);
});
