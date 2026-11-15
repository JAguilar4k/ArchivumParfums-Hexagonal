<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function (): void {
    // A payment received on delivery never reaches a gateway: any outgoing request fails the test.
    Http::preventStrayRequests();
});

/**
 * Record what the owner received on delivery: by default the whole balance of the stored ready order, ₡7.500 by SINPE.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function recordPaymentOnDelivery(string $orderId, array $body = []): TestResponse
{
    return postJson("/api/v1/panel/pedidos/{$orderId}/pagos-manuales", [...['metodo' => 'sinpe', 'monto' => 7500, 'referencia' => 'SINPE-2026-0001'], ...$body]);
}

/** Assert that the order still owes its ₡7.500 and that no payment was recorded for it. */
function expectNothingRecorded(StaffedBusiness $business, string $orderId): void
{
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $orderId, 'paid' => 7500, 'balance' => 7500]);
    expect(PaymentModel::query()->where('order_id', $orderId)->where('gateway', 'manual')->exists())->toBeFalse();
}

it('R29: records the balance paid by SINPE on delivery as a confirmed payment and takes it off the order', function (): void {
    $business = actingAsOwner();
    $order = StoredOrders::create($business, 'ready')['order'];

    $response = recordPaymentOnDelivery($order->id)->assertCreated()
        ->assertJsonPath('data.proposito', 'saldo')
        ->assertJsonPath('data.monto', 7500)
        ->assertJsonPath('data.pasarela', 'manual')
        ->assertJsonPath('data.estado', 'confirmado')
        ->assertJsonPath('data.metodo', 'sinpe')
        ->assertJsonPath('data.referencia', 'SINPE-2026-0001')
        ->assertJsonPath('data.registrado_por.nombre', $business->owner->name);

    $paymentId = (string) $response->json('data.id');
    app(BusinessTransaction::class)->scope($business->business->id);
    $ownerMember = DB::table('members')->where('user_id', $business->owner->id)->value('id');
    assertDatabaseHas('payments', [
        'id' => $paymentId, 'order_id' => $order->id, 'purpose' => 'balance', 'amount' => 7500,
        'gateway' => 'manual', 'method' => null, 'status' => 'succeeded', 'charged_currency' => 'CRC', 'charged_amount' => 7500,
        'provider_reference' => null, 'recorded_by' => $ownerMember, 'manual_method' => 'sinpe', 'manual_reference' => 'SINPE-2026-0001',
    ]);
    expect($response->json('data.registrado_por.id'))->toBe($ownerMember)
        ->and(CarbonImmutable::parse($response->json('data.registrado_en'))->equalTo(PaymentModel::query()->findOrFail($paymentId)->created_at))->toBeTrue();
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready', 'paid' => 15000, 'balance' => 0]);
    assertDatabaseCount('webhook_events', 0);
    Http::assertNothingSent();
});

it('R29: records cash in parts and lets the owner deliver the order once it owes nothing', function (): void {
    $business = actingAsOwner();
    $order = StoredOrders::create($business, 'ready')['order'];

    postJson("/api/v1/panel/pedidos/{$order->id}/entrega")->assertConflict();
    recordPaymentOnDelivery($order->id, ['metodo' => 'efectivo', 'monto' => 5000, 'referencia' => 'Recibo 15'])->assertCreated()
        ->assertJsonPath('data.metodo', 'efectivo')->assertJsonPath('data.monto', 5000);
    postJson("/api/v1/panel/pedidos/{$order->id}/entrega")->assertConflict();
    recordPaymentOnDelivery($order->id, ['metodo' => 'efectivo', 'monto' => 2500, 'referencia' => 'Recibo 16'])->assertCreated();
    postJson("/api/v1/panel/pedidos/{$order->id}/entrega")->assertOk()->assertJsonPath('data.estado', 'entregado')->assertJsonPath('data.saldo', 0);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'paid' => 15000, 'balance' => 0]);
    expect(PaymentModel::query()->where('order_id', $order->id)->where('gateway', 'manual')->orderBy('id')->pluck('manual_reference')->all())->toBe(['Recibo 15', 'Recibo 16'])
        ->and(PaymentModel::query()->where('order_id', $order->id)->where('gateway', 'manual')->pluck('manual_method')->unique()->all())->toBe(['cash']);
    Http::assertNothingSent();
});

it('answers 422 with the detail of each field for a missing reference, an unknown method or an amount that is not a positive integer', function (array $body, string $field): void {
    $business = actingAsOwner();
    $order = StoredOrders::create($business, 'ready')['order'];

    recordPaymentOnDelivery($order->id, $body)->assertUnprocessable()
        ->assertJsonPath('type', url('/problemas/datos-invalidos'))
        ->assertJsonValidationErrorFor($field, 'errores');

    expectNothingRecorded($business, $order->id);
})->with([
    'sin referencia' => [['referencia' => null], 'referencia'],
    'referencia en blanco' => [['referencia' => '   '], 'referencia'],
    'referencia de 256 caracteres' => [['referencia' => str_repeat('a', 256)], 'referencia'],
    'sin método' => [['metodo' => null], 'metodo'],
    'método desconocido' => [['metodo' => 'tarjeta'], 'metodo'],
    'sin monto' => [['monto' => null], 'monto'],
    'monto cero' => [['monto' => 0], 'monto'],
    'monto negativo' => [['monto' => -500], 'monto'],
    'monto con decimales' => [['monto' => 7499.5], 'monto'],
    'monto en texto' => [['monto' => 'siete mil'], 'monto'],
]);

it('R29: answers 409 when the amount is greater than the balance and keeps the balance', function (): void {
    $business = actingAsOwner();
    $order = StoredOrders::create($business, 'ready')['order'];

    recordPaymentOnDelivery($order->id, ['monto' => 7501])->assertConflict()
        ->assertJsonPath('type', url('/problemas/conflicto'))
        ->assertJsonPath('detail', __('payments.over_balance'));

    expectNothingRecorded($business, $order->id);
});

it('R29: answers 409 for an order that is not ready or owes nothing', function (string $status): void {
    $business = actingAsOwner();
    $order = StoredOrders::create($business, $status === 'paid' ? 'ready' : $status)['order'];
    if ($status === 'paid') {
        app(BusinessTransaction::class)->scope($business->business->id);
        $order->update(['paid' => 15000, 'balance' => 0]);
    }

    recordPaymentOnDelivery($order->id)->assertConflict()->assertJsonPath('detail', __('payments.not_payable'));

    app(BusinessTransaction::class)->scope($business->business->id);
    expect(PaymentModel::query()->where('order_id', $order->id)->exists())->toBeFalse();
})->with(['pending_payment', 'confirmed', 'ordered', 'cancelled', 'expired', 'delivered', 'paid']);

it('R29: answers 409 while a refund of the order is pending or an online payment of its balance is in progress', function (string $pending, string $message): void {
    $business = actingAsOwner();
    $order = StoredOrders::create($business, 'ready')['order'];
    if ($pending === 'refund') {
        $deposit = StoredPayments::create($business, $order, 'succeeded');
        app(BusinessTransaction::class)->scope($business->business->id);
        DB::table('refunds')->insert(['id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'payment_id' => $deposit->id, 'amount' => 1000, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    } else {
        StoredPayments::create($business, $order, 'pending', purpose: 'balance');
    }

    recordPaymentOnDelivery($order->id)->assertConflict()->assertJsonPath('detail', __($message));

    expectNothingRecorded($business, $order->id);
})->with([
    'reembolso pendiente' => ['refund', 'payments.refund_pending'],
    'pago en línea en curso' => ['online', 'payments.online_payment_pending'],
]);

it('answers 401 without a session', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business, 'ready')['order'];

    recordPaymentOnDelivery($order->id)->assertUnauthorized();

    expectNothingRecorded($business, $order->id);
});

it('answers 403 to the collaborator and the customer', function (string $role): void {
    $business = actingAsRole($role);
    $order = StoredOrders::create($business, 'ready')['order'];

    recordPaymentOnDelivery($order->id)->assertForbidden()->assertJsonPath('type', url('/problemas/prohibido'));

    expectNothingRecorded($business, $order->id);
})->with(['colaborador', 'cliente']);

it('answers 403 to a token with the ability of the owner whose account is not an owner of the business', function (): void {
    $business = StaffedBusiness::create();
    Sanctum::actingAs($business->staff, Ability::namesGrantedTo(AccountRole::Owner));
    $order = StoredOrders::create($business, 'ready')['order'];

    recordPaymentOnDelivery($order->id)->assertForbidden();

    expectNothingRecorded($business, $order->id);
});

it('answers 404 for an order of another business or one that does not exist', function (string $target): void {
    actingAsOwner();
    $other = StaffedBusiness::create();
    $order = StoredOrders::create($other, 'ready')['order'];

    recordPaymentOnDelivery($target === 'tienda' ? $order->id : (string) Str::uuid7())->assertNotFound()
        ->assertJsonPath('type', url('/problemas/no-encontrado'));

    expectNothingRecorded($other, $order->id);
})->with(['tienda', 'inexistente']);
