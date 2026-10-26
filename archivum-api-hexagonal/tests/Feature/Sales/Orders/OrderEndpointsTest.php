<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('R23: creates a mixed order with server prices and reserves its decant atomically', function () {
    $business = actingAsCustomer();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 15001, 15001]]);
    $variant = $perfume->variants[0]->id->value;
    $bottle = DecantBottleModel::factory()->forPresentation($business->business->id, $variant, 100)->create();
    freezeTime();

    $response = postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', [
        'business_id' => (string) Str::uuid7(), 'cliente_id' => (string) Str::uuid7(),
        'estado' => 'entregado', 'por_cobrar_ahora' => 1,
        'lineas' => [
            ['presentacion_id' => $variant, 'cantidad' => 1, 'precio_unitario' => 1],
            ['botella_id' => $bottle->id, 'ml' => 5, 'precio_unitario' => 1],
        ],
    ]);

    $response->assertCreated()->assertHeader('Location', route('sales.customer.orders.show', ['pedido' => $response->json('data.id')]))
        ->assertJsonPath('data.estado', 'pendiente_de_pago')
        ->assertJsonPath('data.subtotal', 15801)
        ->assertJsonPath('data.por_cobrar_ahora', 8301)
        ->assertJsonPath('data.saldo', 7500)
        ->assertJsonPath('data.lineas.0.precio_unitario', 15001)
        ->assertJsonPath('data.lineas.1.precio_unitario', 800)
        ->assertJsonPath('data.vence_en', now()->addMinutes(30)->format(DATE_ATOM));

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('orders', ['id' => $response->json('data.id'), 'business_id' => $business->business->id, 'subtotal' => 15801, 'paid' => 0]);
    assertDatabaseHas('decant_bottles', ['id' => $bottle->id, 'remaining_ml' => 100, 'reserved_ml' => 5]);
    assertDatabaseHas('decant_movements', ['decant_bottle_id' => $bottle->id, 'order_line_id' => $response->json('data.lineas.1.id'), 'type' => 'reservation', 'ml' => 5]);

    PerfumeVariantModel::query()->whereKey($variant)->update(['sale_price' => 90000]);
    getJson('/api/v1/cuenta/pedidos/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.subtotal', 15801)
        ->assertJsonPath('data.lineas.0.precio_unitario', 15001);
});

it('confirms a free order without a provider', function () {
    $business = actingAsCustomer();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 0, 0]]);

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [
        ['presentacion_id' => $perfume->variants[0]->id->value, 'cantidad' => 1],
    ]])->assertCreated()->assertJsonPath('data.estado', 'confirmado')->assertJsonPath('data.por_cobrar_ahora', 0);

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseCount('payments', 0);
});

it('returns 401 when creating an order without a session', function () {
    $business = StaffedBusiness::create();

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', [])->assertUnauthorized();
});

it('returns 403 to staff accounts when creating or reading customer orders', function (string $role) {
    $business = actingAsRole($role);

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', [])->assertForbidden();
    getJson('/api/v1/cuenta/pedidos')->assertForbidden();
})->with(['dueno', 'colaborador']);

it('returns 422 with line errors for malformed carts', function (array $lines, string $field) {
    $business = actingAsCustomer();

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => $lines])
        ->assertUnprocessable()->assertJsonStructure(['errores' => [$field]]);
})->with([
    'empty' => [[], 'lineas'],
    'no product' => [[['cantidad' => 1]], 'lineas.0.presentacion_id'],
    'invalid uuid' => [[['presentacion_id' => 'invalid', 'cantidad' => 1]], 'lineas.0.presentacion_id'],
    'zero quantity' => [[['presentacion_id' => '019a0010-0000-7000-8000-000000000001', 'cantidad' => 0]], 'lineas.0.cantidad'],
    'fractional ml' => [[['botella_id' => '019a0010-0000-7000-8000-000000000001', 'ml' => 2.5]], 'lineas.0.ml'],
]);

it('returns 422 for inactive, unpriced or foreign presentations', function (string $state) {
    $business = actingAsCustomer();
    $target = $state === 'foreign' ? StaffedBusiness::create() : $business;
    $perfume = StoredPerfumes::create($target->business->id, active: $state !== 'inactive_perfume',
        variants: [[100, $state === 'unpriced' ? null : 15000, null]], inactiveSizes: $state === 'inactive_variant' ? [100] : []);

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [
        ['presentacion_id' => $perfume->variants[0]->id->value, 'cantidad' => 1],
    ]])->assertUnprocessable()->assertJsonPath('errores', ['lineas.0.presentacion_id' => [__('orders.product_unavailable')]]);
})->with(['foreign', 'inactive_perfume', 'inactive_variant', 'unpriced']);

it('returns 422 for an empty or foreign bottle and for an unsupported decant size', function (string $state) {
    $business = actingAsCustomer();
    $other = $state === 'foreign' ? StaffedBusiness::create() : $business;
    $bottle = DecantBottleModel::factory()->create(['business_id' => $other->business->id,
        'remaining_ml' => $state === 'empty' ? 0 : 100, 'status' => $state === 'empty' ? 'empty' : 'open']);

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [
        ['botella_id' => $bottle->id, 'ml' => $state === 'size' ? 3 : 5],
    ]])->assertUnprocessable()->assertJsonStructure(['errores']);
})->with(['empty', 'foreign', 'size']);

it('returns 409 and rolls back the complete cart when the second bottle is short', function () {
    $business = actingAsCustomer();
    $first = DecantBottleModel::factory()->create(['business_id' => $business->business->id]);
    $second = DecantBottleModel::factory()->withRemainingMl(2)->create(['business_id' => $business->business->id]);

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [
        ['botella_id' => $first->id, 'ml' => 5],
        ['botella_id' => $second->id, 'ml' => 5],
    ]])->assertConflict();

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseCount('orders', 0);
    assertDatabaseCount('order_lines', 0);
    assertDatabaseCount('decant_movements', 0);
    assertDatabaseHas('decant_bottles', ['id' => $first->id, 'reserved_ml' => 0, 'remaining_ml' => 100]);
});

it('lists only the customer own orders across two businesses with global pagination', function () {
    $first = actingAsCustomer();
    $second = StaffedBusiness::create();
    $own = StoredOrders::create($first);
    $foreign = StoredOrders::create($second);
    $this->scopeQueriesTo($second->business->id);
    CustomerModel::factory()->create(['business_id' => $second->business->id, 'user_id' => $first->customer->id]);
    $newCustomer = CustomerModel::query()->where('business_id', $second->business->id)->where('user_id', $first->customer->id)->firstOrFail();
    $secondOwn = OrderModel::factory()->create(['business_id' => $second->business->id, 'customer_id' => $newCustomer->id]);

    $response = getJson('/api/v1/cuenta/pedidos?por_pagina=1');

    $response->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($secondOwn->id);
    getJson('/api/v1/cuenta/pedidos?por_pagina=1&pagina=2')->assertOk()->assertJsonPath('data.0.id', $own['order']->id);
    getJson('/api/v1/cuenta/pedidos/'.$foreign['order']->id)->assertNotFound();
});

it('returns 404 for another customer order in the same business', function () {
    $business = actingAsCustomer();
    $own = StoredOrders::create($business);
    $other = UserModel::factory()->create();
    $this->scopeQueriesTo($business->business->id);
    CustomerModel::factory()->create(['business_id' => $business->business->id, 'user_id' => $other->id]);
    Sanctum::actingAs($other, ['*']);

    getJson('/api/v1/cuenta/pedidos/'.$own['order']->id)->assertNotFound();
    postJson('/api/v1/cuenta/pedidos/'.$own['order']->id.'/cancelacion')->assertNotFound();
    getJson('/api/v1/cuenta/pedidos')->assertOk()->assertJsonCount(0, 'data');
});

it('filters owner orders using Spanish states and conceals other tenants', function () {
    $first = actingAsOwner();
    $second = StaffedBusiness::create();
    $confirmed = StoredOrders::create($first, 'confirmed');
    StoredOrders::create($first);
    $foreign = StoredOrders::create($second, 'confirmed');

    getJson('/api/v1/panel/pedidos?estado=confirmado')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $confirmed['order']->id)->assertJsonPath('data.0.cliente.nombre', $first->customer->name);
    getJson('/api/v1/panel/pedidos?estado=confirmed')->assertUnprocessable()->assertJsonStructure(['errores' => ['estado']]);
    getJson('/api/v1/panel/pedidos/'.$foreign['order']->id)->assertNotFound();
});

it('returns 403 to staff and customers on every owner order action', function (string $role) {
    $business = actingAsRole($role);
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $root = '/api/v1/panel/pedidos/'.$order->id;

    getJson('/api/v1/panel/pedidos')->assertForbidden();
    getJson($root)->assertForbidden();
    foreach (['preparacion', 'listo', 'entrega', 'lineas/'.Str::uuid7().'/no-disponible'] as $path) {
        postJson($root.'/'.$path)->assertForbidden();
    }
})->with(['colaborador', 'cliente']);

it('R26: consumes a reserved decant once and automatically readies a decant-only order', function () {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'confirmed', false, true);
    $bottle = $fixture['bottle'];
    $root = '/api/v1/panel/pedidos/'.$fixture['order']->id;

    postJson($root.'/preparacion')->assertOk()->assertJsonPath('data.estado', 'listo')
        ->assertJsonPath('data.lineas.0.estado', 'preparada');
    postJson($root.'/preparacion')->assertConflict();

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $bottle?->id, 'remaining_ml' => 95, 'reserved_ml' => 0]);
    assertDatabaseCount('decant_movements', 1);
});

it('leaves a mixed order confirmed after preparing its decants', function () {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'confirmed', true, true);

    postJson('/api/v1/panel/pedidos/'.$fixture['order']->id.'/preparacion')->assertOk()
        ->assertJsonPath('data.estado', 'confirmado');
});

it('returns 409 for preparation without a paid active order or without reserved decants', function (string $status, bool $decants) {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, $status, true, $decants);

    postJson('/api/v1/panel/pedidos/'.$fixture['order']->id.'/preparacion')->assertConflict();
})->with([
    ['pending_payment', true], ['cancelled', true], ['expired', true], ['confirmed', false], ['delivered', true],
]);

it('marks the residual mixed order ready only after every bottle arrived and every decant was prepared', function () {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'ordered', true, true);
    $root = '/api/v1/panel/pedidos/'.$fixture['order']->id;
    $this->scopeQueriesTo($business->business->id);
    OrderLineModel::query()->where('order_id', $fixture['order']->id)->where('kind', 'bottle')->update(['status' => 'received']);

    postJson($root.'/listo')->assertConflict();
    postJson($root.'/preparacion')->assertOk()->assertJsonPath('data.estado', 'encargado');
    postJson($root.'/listo')->assertOk()->assertJsonPath('data.estado', 'listo');
    postJson($root.'/listo')->assertConflict();
});

it('R28: returns 409 on delivery with a balance and delivers only a fully paid ready order', function () {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'ready');
    $root = '/api/v1/panel/pedidos/'.$fixture['order']->id;
    postJson($root.'/entrega')->assertConflict()->assertJsonPath('detail', __('orders.balance_due'));
    $this->scopeQueriesTo($business->business->id);
    $fixture['order']->update(['paid' => 15000, 'balance' => 0]);

    postJson($root.'/entrega')->assertOk()->assertJsonPath('data.estado', 'entregado');
    postJson($root.'/entrega')->assertConflict();
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => 'delivered']);
});

it('cancels an unpaid order and releases its reserved ml in the same transaction', function () {
    $business = actingAsCustomer();
    $fixture = StoredOrders::create($business, 'pending_payment', true, true);
    freezeTime();

    postJson('/api/v1/cuenta/pedidos/'.$fixture['order']->id.'/cancelacion')->assertOk()
        ->assertJsonPath('data.estado', 'cancelado')->assertJsonPath('data.lineas.1.estado', 'liberada')
        ->assertJsonPath('data.cancelado_en', now()->format(DATE_ATOM));

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $fixture['bottle']?->id, 'remaining_ml' => 100, 'reserved_ml' => 0]);
    assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => 'cancelled']);
    assertDatabaseCount('refunds', 0);
});

it('returns 409 when cancelling an ordered, ready or terminal order', function (string $status) {
    $business = actingAsCustomer();
    $fixture = StoredOrders::create($business, $status);

    postJson('/api/v1/cuenta/pedidos/'.$fixture['order']->id.'/cancelacion')->assertConflict();
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => $status]);
})->with(['ordered', 'ready', 'delivered', 'cancelled', 'expired']);

it('R30: stores a pending refund tied to the original successful payment on cancellation', function () {
    $business = actingAsCustomer();
    $fixture = StoredOrders::create($business, 'confirmed');
    $this->scopeQueriesTo($business->business->id);
    $paymentId = DB::table('payments')->insertGetId([
        'business_id' => $business->business->id, 'order_id' => $fixture['order']->id, 'purpose' => 'deposit',
        'gateway' => 'onvo', 'amount' => 7500, 'charged_amount' => 750000, 'status' => 'succeeded',
        'provider_reference' => 'onvo-cancel-test', 'created_at' => now(), 'updated_at' => now(),
    ]);

    postJson('/api/v1/cuenta/pedidos/'.$fixture['order']->id.'/cancelacion')->assertOk()->assertJsonPath('data.pagado', 7500);

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $paymentId, 'amount' => 7500, 'status' => 'pending']);
});

it('does not deliver a nominally paid order while a refund may still remove its credit', function () {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'ready');
    $this->scopeQueriesTo($business->business->id);
    $fixture['order']->update(['paid' => 15000, 'balance' => 0]);
    $paymentId = DB::table('payments')->insertGetId(['business_id' => $business->business->id, 'order_id' => $fixture['order']->id,
        'purpose' => 'full', 'gateway' => 'onvo', 'amount' => 15000, 'charged_amount' => 1500000, 'status' => 'succeeded',
        'provider_reference' => 'delivery-pending-refund', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('refunds')->insert(['business_id' => $business->business->id, 'payment_id' => $paymentId, 'amount' => 1000, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

    postJson('/api/v1/panel/pedidos/'.$fixture['order']->id.'/entrega')->assertConflict()->assertJsonPath('detail', __('orders.refund_pending'));

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => 'ready', 'paid' => 15000]);
});

it('marks an ordered bottle unavailable and requests only its allocated deposit', function () {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'ordered', true, true);
    $this->scopeQueriesTo($business->business->id);
    $line = OrderLineModel::query()->where('order_id', $fixture['order']->id)->where('kind', 'bottle')->firstOrFail();
    $paymentId = DB::table('payments')->insertGetId([
        'business_id' => $business->business->id, 'order_id' => $fixture['order']->id, 'purpose' => 'deposit',
        'gateway' => 'onvo', 'amount' => 8300, 'charged_amount' => 830000, 'status' => 'succeeded',
        'provider_reference' => 'onvo-unavailable-test', 'created_at' => now(), 'updated_at' => now(),
    ]);

    postJson('/api/v1/panel/pedidos/'.$fixture['order']->id.'/lineas/'.$line->id.'/no-disponible')
        ->assertOk()->assertJsonPath('data.subtotal', 800)->assertJsonPath('data.saldo', 0)
        ->assertJsonPath('data.lineas.0.estado', 'no_disponible');

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $paymentId, 'order_line_id' => $line->id, 'amount' => 7500, 'status' => 'pending']);
});

it('R31: cancels an order with no available lines and reserves a refund of all paid money', function (int $paid) {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'ordered');
    $this->scopeQueriesTo($business->business->id);
    $fixture['order']->update(['paid' => $paid, 'balance' => 15000 - $paid]);
    $line = OrderLineModel::query()->where('order_id', $fixture['order']->id)->firstOrFail();
    $paymentId = DB::table('payments')->insertGetId([
        'business_id' => $business->business->id, 'order_id' => $fixture['order']->id, 'purpose' => $paid === 15000 ? 'full' : 'deposit',
        'gateway' => 'onvo', 'amount' => $paid, 'charged_amount' => $paid * 100, 'status' => 'succeeded',
        'provider_reference' => 'unavailable-last-line-'.$paid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    freezeTime();

    postJson('/api/v1/panel/pedidos/'.$fixture['order']->id.'/lineas/'.$line->id.'/no-disponible')
        ->assertOk()->assertJsonPath('data.estado', 'cancelado')->assertJsonPath('data.subtotal', 0)
        ->assertJsonPath('data.cancelado_en', now()->format(DATE_ATOM))->assertJsonPath('data.pagado', $paid);

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $paymentId, 'amount' => $paid, 'status' => 'pending']);
    assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => 'cancelled', 'paid' => $paid]);
})->with([7500, 15000]);

it('reserves only the remaining refund when earlier refunds are pending or completed', function (bool $completed) {
    $business = actingAsOwner();
    $fixture = StoredOrders::create($business, 'ordered');
    $this->scopeQueriesTo($business->business->id);
    $fixture['order']->update(['subtotal' => 30000, 'due_now' => 15000, 'paid' => 15000, 'balance' => 15000]);
    $first = OrderLineModel::query()->where('order_id', $fixture['order']->id)->firstOrFail();
    $second = OrderLineModel::query()->create(['id' => (string) Str::uuid7(), 'business_id' => $business->business->id,
        'order_id' => $fixture['order']->id, 'kind' => 'bottle', 'perfume_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'unit_price' => 15000, 'status' => 'ordered']);
    $paymentId = DB::table('payments')->insertGetId(['business_id' => $business->business->id, 'order_id' => $fixture['order']->id,
        'purpose' => 'deposit', 'gateway' => 'onvo', 'amount' => 15000, 'charged_amount' => 1500000, 'status' => 'succeeded',
        'provider_reference' => 'two-unavailable-lines', 'created_at' => now(), 'updated_at' => now()]);
    $root = '/api/v1/panel/pedidos/'.$fixture['order']->id.'/lineas/';

    postJson($root.$first->id.'/no-disponible')->assertOk()->assertJsonPath('data.estado', 'encargado');
    $this->scopeQueriesTo($business->business->id);
    if ($completed) {
        DB::table('refunds')->where('payment_id', $paymentId)->update(['status' => 'succeeded']);
        DB::table('payments')->where('id', $paymentId)->update(['status' => 'partially_refunded', 'refunded_amount' => 7500]);
        OrderModel::query()->whereKey($fixture['order']->id)->update(['paid' => 7500]);
    }

    postJson($root.$second->id.'/no-disponible')->assertOk()->assertJsonPath('data.estado', 'cancelado');

    $this->scopeQueriesTo($business->business->id);
    assertDatabaseCount('refunds', 2);
    assertDatabaseHas('refunds', ['payment_id' => $paymentId, 'order_line_id' => $second->id, 'amount' => 7500, 'status' => 'pending']);
    expect((int) DB::table('refunds')->where('payment_id', $paymentId)->sum('amount'))->toBe(15000);
})->with([false, true]);

it('rejects negative monetary data and cross-business customer references at the database boundary', function (string $field) {
    $business = StaffedBusiness::create();
    $fixture = StoredOrders::create($business);
    $this->scopeQueriesTo($business->business->id);

    expect(fn () => DB::transaction(fn () => DB::table('orders')->where('id', $fixture['order']->id)->update([$field => -1])))
        ->toThrow(QueryException::class, 'orders_'.$field.'_check');
})->with(['subtotal', 'due_now', 'paid', 'balance']);

it('does not let the application role insert incoherent order lines', function () {
    $business = StaffedBusiness::create();
    $fixture = StoredOrders::create($business);
    $this->scopeQueriesTo($business->business->id);
    $line = OrderLineModel::query()->where('order_id', $fixture['order']->id)->firstOrFail();

    expect(fn () => DB::transaction(fn () => DB::table('order_lines')->where('id', $line->id)->update(['kind' => 'decant'])))
        ->toThrow(QueryException::class, 'order_lines_kind_check');
});

it('does not let a line reference a presentation from another business', function () {
    $first = StaffedBusiness::create();
    $second = StaffedBusiness::create();
    $own = StoredOrders::create($first);
    $foreign = StoredOrders::create($second);
    $this->scopeQueriesTo($first->business->id);
    $line = OrderLineModel::query()->where('order_id', $own['order']->id)->firstOrFail();

    expect(fn () => DB::transaction(fn () => DB::table('order_lines')->where('id', $line->id)->update(['perfume_variant_id' => $foreign['variant_id']])))
        ->toThrow(QueryException::class, 'order_lines_perfume_variant_id_business_id_foreign');
});

it('resolves an own order business before RLS and returns null for another customer', function () {
    $business = StaffedBusiness::create();
    $fixture = StoredOrders::create($business);
    $this->scopeQueriesTo(null);

    expect(DB::scalar("select fn_customer_resource_business('order', ?, ?)", [$fixture['order']->id, $business->customer->id]))->toBe($business->business->id);
    expect(DB::scalar("select fn_customer_resource_business('order', ?, ?)", [$fixture['order']->id, $business->staff->id]))->toBeNull();
    expect(DB::table('orders')->count())->toBe(0);
});
