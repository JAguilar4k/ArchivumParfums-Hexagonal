<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Payments\FailPaymentSave;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function (): void {
    config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.paypal.client_id' => 'fake-client', 'payments.paypal.client_secret' => 'fake-secret']);
    Http::preventStrayRequests();
});

it('creates the server-calculated charge and ignores browser amounts states and tenant', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, withDecant: true)['order'];
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-1', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-1', 'paymentIntentId' => 'intent-1'], 201)]);

    $response = postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos', ['monto' => 1, 'proposito' => 'saldo', 'estado' => 'confirmado', 'business_id' => (string) Str::uuid7(), 'pasarela' => 'paypal']);

    $response->assertCreated()->assertJsonPath('data.monto', 8300)->assertJsonPath('data.proposito', 'deposito')
        ->assertJsonPath('data.pasarela', 'onvo')->assertJsonPath('data.estado', 'pendiente')
        ->assertHeader('Location', route('payments.show', ['pago' => $response->json('data.id')]));
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    assertDatabaseHas('payments', ['id' => $response->json('data.id'), 'business_id' => $business->business->id, 'amount' => 8300, 'status' => 'pending']);
    Http::assertSent(fn (Request $request): bool => $request['lineItems'][0]['unitAmount'] === 830000 && $request['metadata']['businessId'] === $business->business->id);
});

it('charges a decant-only order as a full payment', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, withBottle: false, withDecant: true)['order'];
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-1', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-1'], 201)]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertCreated()->assertJsonPath('data.proposito', 'total')->assertJsonPath('data.monto', 800);

    Http::assertSentCount(1);
});

it('creates and confirms a SINPE Mobile payment through the API with a checkout that omits mode', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-sinpe', 'url' => 'https://checkout.onvopay.com/pay/session-sinpe'], 201),
        'api.onvopay.com/v1/checkout/sessions/session-sinpe' => Http::response(['id' => 'session-sinpe', 'paymentIntentId' => 'intent-sinpe']),
        'api.onvopay.com/v1/payment-intents/intent-sinpe' => Http::response([
            'id' => 'intent-sinpe', 'mode' => 'test', 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded',
            'paymentMethod' => ['id' => 'method-sinpe', 'mode' => 'test', 'type' => 'mobile_number'],
        ]),
    ]);

    $response = postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos', ['medio' => 'sinpe_movil']);

    $response->assertCreated()->assertJsonPath('data.medio', 'sinpe_movil')->assertJsonPath('data.estado', 'pendiente')
        ->assertJsonPath('data.pasarela', 'onvo')->assertJsonPath('data.moneda_cobrada', 'CRC')->assertJsonPath('data.monto_cobrado', 7500);
    $paymentId = (string) $response->json('data.id');
    getJson('/api/v1/cuenta/pagos/'.$paymentId)->assertOk()->assertJsonPath('data.medio', 'sinpe_movil')
        ->assertJsonPath('data.estado', 'confirmado')->assertJsonPath('data.consulta_pendiente', false);
    getJson('/api/v1/cuenta/pagos/'.$paymentId)->assertOk()->assertJsonPath('data.estado', 'confirmado');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $paymentId, 'order_id' => $order->id, 'method' => 'sinpe_mobile', 'status' => 'succeeded', 'capture_reference' => 'intent-sinpe']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500, 'balance' => 7500]);
    assertDatabaseCount('payments', 1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.onvopay.com/v1/checkout/sessions/one-time-link'
        && $request['paymentMethodTypes'] === ['mobile_number']
        && $request['lineItems'][0]['unitAmount'] === 750_000);
    Http::assertSentCount(3);
});

it('confirms the queried payment and its order exactly once in the same transaction', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id.'?estado=confirmado')->assertOk()->assertJsonPath('data.estado', 'confirmado');
    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertOk()->assertJsonPath('data.estado', 'confirmado');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500, 'balance' => 7500]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    Http::assertSentCount(2);
});

it('records an explicit refusal while leaving the order unpaid', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['code' => 'declined'], 402)]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertCreated()->assertJsonPath('data.estado', 'fallido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    Http::assertSentCount(1);
});

it('records a late approval and one full refund without reactivating a closed order', function (string $status): void {
    $business = actingAsCustomer();
    $fixture = StoredOrders::create($business, withDecant: true);
    $order = $fixture['order'];
    $payment = StoredPayments::create($business, $order, amount: 8300);
    if ($status === 'cancelled') {
        postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    } else {
        app(BusinessTransaction::class)->scope($business->business->id);
        $order->update(['status' => 'expired']);
        $fixture['bottle']?->update(['reserved_ml' => 0]);
        DB::table('order_lines')->where('order_id', $order->id)->where('kind', 'decant')->update(['status' => 'released']);
    }
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 830000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertOk()->assertJsonPath('data.estado', 'confirmado');
    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertOk();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => $status, 'paid' => 8300]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 8300, 'status' => 'pending']);
    assertDatabaseCount('refunds', 1);
    assertDatabaseHas('decant_bottles', ['id' => $fixture['bottle']?->id, 'remaining_ml' => 100, 'reserved_ml' => 0]);
    Http::assertSentCount(2);
})->with(['cancelled', 'expired']);

it('does not capture a merely approved PayPal order after the local order was cancelled', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order, gateway: 'paypal');
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'status' => 'APPROVED', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '15.00']]]]),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertOk()->assertJsonPath('data.estado', 'pendiente');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    assertDatabaseCount('refunds', 0);
    Http::assertSentCount(2);
});

it('queries an already created payment after the initiation deadline instead of trusting the local clock', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    app(BusinessTransaction::class)->scope($business->business->id);
    $order->update(['expires_at' => now()->subMinute()]);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertOk()->assertJsonPath('data.estado', 'confirmado');
    Http::assertSentCount(2);
});

it('reuses the pending payment when a declined hosted checkout cannot yet be closed safely', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'open', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750000, 'currency' => 'CRC', 'status' => 'requires_payment_method', 'confirmationAttempts' => 1]),
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference.'/expire' => Http::failedConnection(),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference.'/cancel' => Http::failedConnection(),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertAccepted()->assertJsonPath('data.estado', 'pendiente');
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertOk()->assertJsonPath('data.id', $payment->id);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseCount('payments', 1);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/one-time-link'));
});

it('retains an uncertain creation and returns the same attempt without a second POST', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::failedConnection()]);

    $first = postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertAccepted()->assertHeader('Retry-After', '30')->assertJsonPath('data.consulta_pendiente', true);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertAccepted()->assertJsonPath('data.id', $first->json('data.id'));
    getJson('/api/v1/cuenta/pagos/'.$first->json('data.id'))->assertAccepted()->assertJsonPath('data.consulta_pendiente', true);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseCount('payments', 1);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0]);
    Http::assertSentCount(1);
});

it('keeps a payment pending when the authoritative query cannot communicate', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    Http::fake(['api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::failedConnection()]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertAccepted()->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('data.consulta_pendiente', true);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0]);
    Http::assertSentCount(1);
});

it('returns 422 for a mismatched provider amount without confirming either record', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 7500, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertUnprocessable()->assertJsonPath('detail', __('payments.invalid_amount'));

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0]);
    Http::assertSentCount(2);
});

it('returns 422 before creating an attempt when sandbox credentials are missing', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    config(['payments.onvo.secret_key' => '']);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertUnprocessable()->assertJsonPath('detail', __('payments.invalid_configuration'));

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseCount('payments', 0);
    Http::assertNothingSent();
});

it('returns 422 with Spanish validation errors for an unsupported submitted method', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos', ['medio' => 'efectivo'])->assertUnprocessable()->assertJsonStructure(['errores' => ['medio']]);

    Http::assertNothingSent();
});

it('switches providers only through the business configuration', function (): void {
    $business = actingAsCustomer();
    $firstOrder = StoredOrders::create($business)['order'];
    $secondOrder = StoredOrders::create($business)['order'];
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-1', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-1'], 201),
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'order-paypal', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=order-paypal']]], 201),
    ]);

    postJson('/api/v1/cuenta/pedidos/'.$firstOrder->id.'/pagos')->assertCreated()->assertJsonPath('data.pasarela', 'onvo');
    $business->business->update(['settings' => ['default_gateway' => 'paypal', 'usd_exchange_rate' => 500]]);
    postJson('/api/v1/cuenta/pedidos/'.$secondOrder->id.'/pagos')->assertCreated()->assertJsonPath('data.pasarela', 'paypal')->assertJsonPath('data.moneda_cobrada', 'USD')->assertJsonPath('data.monto_cobrado', 1500);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['order_id' => $firstOrder->id, 'gateway' => 'onvo']);
    assertDatabaseHas('payments', ['order_id' => $secondOrder->id, 'gateway' => 'paypal', 'charged_amount' => 1500, 'exchange_rate' => '500.000000']);
    Http::assertSentCount(3);
});

it('returns 422 for SINPE on PayPal or an unset conversion rate', function (bool $withRate): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $business->business->update(['settings' => ['default_gateway' => 'paypal', 'usd_exchange_rate' => $withRate ? 500 : null]]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos', ['medio' => $withRate ? 'sinpe_movil' : 'tarjeta'])->assertUnprocessable()->assertJsonPath('detail', __($withRate ? 'payments.unsupported_method' : 'payments.invalid_exchange_rate'));

    Http::assertNothingSent();
})->with([true, false]);

it('rejects unrepresentable dollar amounts before committing an attempt or making an HTTP request', function (int $amount, int $rate): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    app(BusinessTransaction::class)->scope($business->business->id);
    $order->update(['due_now' => $amount]);
    $business->business->update(['settings' => ['default_gateway' => 'paypal', 'usd_exchange_rate' => $rate]]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertUnprocessable();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseCount('payments', 0);
    Http::assertNothingSent();
})->with([[1, 500], [7500, 999999999]]);

it('returns 401 without a session for charge query and refund actions', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertUnauthorized();
    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertUnauthorized();
    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertUnauthorized();

    Http::assertNothingSent();
});

it('returns 403 to staff roles on customer payment routes', function (string $role): void {
    $business = actingAsRole($role);
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertForbidden();
    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertForbidden();

    Http::assertNothingSent();
})->with(['dueno', 'colaborador']);

it('returns 403 to customers and collaborators on owner refunds', function (string $role): void {
    $business = actingAsRole($role);
    $order = StoredOrders::create($business)['order'];

    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertForbidden();

    Http::assertNothingSent();
})->with(['cliente', 'colaborador']);

it('returns 404 for another customer or tenant resource and for unknown identities', function (string $target): void {
    $business = actingAsCustomer();
    $other = $target === 'tenant' ? StaffedBusiness::create() : $business;
    $order = StoredOrders::create($other)['order'];
    $payment = StoredPayments::create($other, $order);
    if ($target === 'customer') {
        $user = UserModel::factory()->create();
        CustomerModel::factory()->create(['business_id' => $business->business->id, 'user_id' => $user->id]);
        Sanctum::actingAs($user, ['*']);
    }
    $orderId = $target === 'missing' ? (string) Str::uuid7() : $order->id;
    $paymentId = $target === 'missing' ? (string) Str::uuid7() : $payment->id;

    postJson('/api/v1/cuenta/pedidos/'.$orderId.'/pagos')->assertNotFound()->assertJsonPath('type', url('/problemas/no-encontrado'));
    getJson('/api/v1/cuenta/pagos/'.$paymentId)->assertNotFound();

    Http::assertNothingSent();
})->with(['customer', 'tenant', 'missing']);

it('returns 409 for a cancelled expired confirmed delivered or elapsed order', function (string $status): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, $status === 'elapsed' ? 'pending_payment' : $status)['order'];
    if ($status === 'elapsed') {
        app(BusinessTransaction::class)->scope($business->business->id);
        $order->update(['expires_at' => now()->subSecond()]);
    }

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertConflict();

    Http::assertNothingSent();
})->with(['cancelled', 'expired', 'confirmed', 'delivered', 'elapsed']);

it('R29: permits the ready balance and calculates it from paid money', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'ready')['order'];
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-balance', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-balance'], 201)]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos', ['monto' => 1])->assertCreated()->assertJsonPath('data.proposito', 'saldo')->assertJsonPath('data.monto', 7500);

    Http::assertSentCount(1);
});

it('rolls back the order update when saving the confirmed payment fails', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    app()->bind(PaymentRepository::class, fn (): FailPaymentSave => new FailPaymentSave(app(EloquentPaymentRepository::class)));
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    getJson('/api/v1/cuenta/pagos/'.$payment->id)->assertInternalServerError();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0, 'status' => 'pending_payment']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    Http::assertSentCount(2);
});

it('retains the committed attempt if saving its external reference fails', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    app()->bind(PaymentRepository::class, fn (): FailPaymentSave => new FailPaymentSave(app(EloquentPaymentRepository::class), afterReference: true));
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-uncertain', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-uncertain'], 201)]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertInternalServerError();
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertAccepted()->assertJsonPath('data.consulta_pendiente', true);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseCount('payments', 1);
    assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending', 'provider_reference' => null]);
    Http::assertSentCount(1);
});

it('refunds the server allocation from a paid cancellation once and ignores a browser amount', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-1'], 201),
        'api.onvopay.com/v1/refunds/refund-1' => Http::response(['id' => 'refund-1', 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference, 'amount' => 750000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    Sanctum::actingAs($business->owner, ['*']);

    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos', ['monto' => 1])->assertOk()->assertJsonPath('data.0.monto', 7500)->assertJsonPath('data.0.estado', 'confirmado');
    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertOk()->assertJsonCount(0, 'data');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'succeeded']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['amount'] === 750000);
    Http::assertSentCount(2);
});

it('does not resend an uncertain refund POST and preserves the money as not yet returned', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    Http::fake(['api.onvopay.com/v1/refunds' => Http::failedConnection()]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    Sanctum::actingAs($business->owner, ['*']);

    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertOk()->assertJsonPath('data.0.estado', 'pendiente');
    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertOk()->assertJsonPath('data.0.estado', 'pendiente');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'pending', 'provider_reference' => null]);
    expect(DB::table('refunds')->where('payment_id', $payment->id)->value('attempted_at'))->not->toBeNull();
    Http::assertSentCount(1);
});

it('preserves a refund reference across financial rollback and retries only the safe query', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-durable'], 201),
        'api.onvopay.com/v1/refunds/refund-durable' => Http::response(['id' => 'refund-durable', 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference, 'amount' => 750000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    Sanctum::actingAs($business->owner, ['*']);
    app()->bind(PaymentRepository::class, fn (): FailPaymentSave => new FailPaymentSave(app(EloquentPaymentRepository::class), afterReference: true));

    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertInternalServerError();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'pending', 'provider_reference' => 'refund-durable']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 7500]);
    app()->bind(PaymentRepository::class, EloquentPaymentRepository::class);
    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertOk()->assertJsonPath('data.0.estado', 'confirmado');
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0]);
    Http::assertSentCount(3);
});

it('returns 409 for a balance while the order still has an unresolved refund', function (string $status): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'ready')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    app(BusinessTransaction::class)->scope($business->business->id);
    DB::table('refunds')->insert(['id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'payment_id' => $payment->id, 'amount' => 500, 'status' => $status]);

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/pagos')->assertConflict();

    Http::assertNothingSent();
})->with(['pending', 'failed']);

it('returns 404 to the owner for foreign and missing refund resources', function (): void {
    $business = actingAsOwner();
    $other = StaffedBusiness::create();
    $order = StoredOrders::create($other, 'confirmed')['order'];

    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertNotFound();
    postJson('/api/v1/panel/pedidos/'.Str::uuid7().'/reembolsos')->assertNotFound();

    Http::assertNothingSent();
});
