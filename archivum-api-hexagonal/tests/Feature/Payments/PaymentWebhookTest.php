<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Infrastructure\Jobs\ProcessPaymentEventJob;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Payments\FailPaymentSave;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

// These tests use the sync queue, so the job of each kept notice runs inside its request; PaymentEventQueueTest
// sends them through the database queue of production.
beforeEach(function (): void {
    config([
        'payments.onvo.secret_key' => 'onvo_test_fake',
        'payments.onvo.webhook_secret' => 'webhook_secret_fictitious',
        'payments.paypal.client_id' => 'fake-client',
        'payments.paypal.client_secret' => 'fake-secret',
        'payments.paypal.webhook_id' => 'WH-FICTITIOUS',
    ]);
});

/**
 * A notice as ONVO sends it: its type and the object it is about, in test mode.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function onvoNotice(string $type, array $data): array
{
    return ['type' => $type, 'data' => ['mode' => 'test', 'currency' => 'CRC', ...$data]];
}

/**
 * The paid checkout of a stored payment, with the metadata Archivum sent when it created the charge.
 *
 * @return array<string, mixed>
 */
function paidOnvoCheckout(StaffedBusiness $business, PaymentModel $payment, int $amountTotal = 750_000, string $currency = 'CRC'): array
{
    return onvoNotice('checkout-session.succeeded', [
        'id' => $payment->provider_reference, 'status' => 'complete', 'paymentStatus' => 'paid',
        'amountTotal' => $amountTotal, 'currency' => $currency,
        'metadata' => ['businessId' => $business->business->id, 'paymentId' => $payment->id],
    ]);
}

/**
 * Send a notice to the ONVO webhook, with the secret ONVO would send or none.
 *
 * @param  array<string, mixed>  $notice
 * @return TestResponse<JsonResponse>
 */
function postOnvoNotice(array $notice, ?string $secret = 'webhook_secret_fictitious'): TestResponse
{
    return postJson('/api/v1/webhooks/onvo', $notice, $secret === null ? [] : ['X-Webhook-Secret' => $secret]);
}

/** ONVO answering the server's own query about the checkout of a stored payment. */
function fakeOnvoCheckout(PaymentModel $payment, string $intentStatus = 'succeeded', string $sessionStatus = 'complete'): void
{
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => $sessionStatus, 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => $payment->charged_amount * 100, 'currency' => 'CRC', 'status' => $intentStatus]),
    ]);
}

/**
 * A notice as PayPal sends it, with its own identifier.
 *
 * @param  array<string, mixed>  $resource
 * @return array<string, mixed>
 */
function paypalNotice(string $type, array $resource, string $id = 'WH-FICTITIOUS-EVENT-1'): array
{
    return ['id' => $id, 'event_version' => '1.0', 'event_type' => $type, 'resource' => $resource];
}

/**
 * The headers PayPal signs a notice with; the values are fictitious and PayPal's verification API is faked.
 *
 * @return array<string, string>
 */
function paypalSignature(): array
{
    return [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-fictitious',
        'PAYPAL-TRANSMISSION-ID' => 'transmission-1', 'PAYPAL-TRANSMISSION-SIG' => 'fictitious-signature', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-01T12:00:00Z',
    ];
}

/** Run one job of the database queue, as the worker of production does. */
function workOnePaymentEvent(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

/**
 * The rows a rejected notice must not touch.
 *
 * @return array<string, mixed>
 */
function webhookState(StaffedBusiness $business): array
{
    app(BusinessTransaction::class)->scope($business->business->id);

    return [
        'payments' => DB::table('payments')->orderBy('id')->get()->all(),
        'orders' => DB::table('orders')->orderBy('id')->get()->all(),
        'webhook_events' => DB::table('webhook_events')->get()->all(),
    ];
}

it('R38: rejects an ONVO notice whose origin is not verified and changes nothing', function (?string $secret, array $settings): void {
    config($settings);
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order']);
    $before = webhookState($business);

    postOnvoNotice(paidOnvoCheckout($business, $payment), $secret)->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas')
        ->assertJsonPath('detail', 'No se pudo verificar el origen del aviso.');

    expect(webhookState($business))->toEqual($before);
    Http::assertNothingSent();
})->with([
    'without the header' => [null, []],
    'with another secret' => ['webhook_secret_other', []],
    'with no secret configured' => ['', ['payments.onvo.webhook_secret' => '']],
    'with no test key configured' => ['webhook_secret_fictitious', ['payments.onvo.secret_key' => '']],
]);

it('R38: rejects a PayPal notice whose signature PayPal does not verify and changes nothing', function (array $answer, int $status): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order'], gateway: 'paypal');
    $before = webhookState($business);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response($answer, $status),
    ]);
    $notice = paypalNotice('CHECKOUT.ORDER.APPROVED', ['id' => $payment->provider_reference, 'status' => 'APPROVED', 'purchase_units' => [[
        'custom_id' => $business->business->id.':'.$payment->id, 'amount' => ['currency_code' => 'USD', 'value' => '15.00'],
    ]]]);

    postJson('/api/v1/webhooks/paypal', $notice, paypalSignature())->assertUnauthorized()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas')
        ->assertJsonPath('detail', 'No se pudo verificar el origen del aviso.');
    postJson('/api/v1/webhooks/paypal', $notice)->assertUnauthorized();

    expect(webhookState($business))->toEqual($before);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/verify-webhook-signature') && $request['webhook_id'] === 'WH-FICTITIOUS');
    // The notice without signature headers is rejected before PayPal is asked.
    Http::assertSentCount(2);
})->with([
    'a failed verification' => [['verification_status' => 'FAILURE'], 200],
    'a refused request' => [['name' => 'VALIDATION_ERROR', 'message' => 'Invalid request - see details.'], 400],
    'an unprocessable request' => [['name' => 'UNPROCESSABLE_ENTITY', 'message' => 'The requested action could not be performed.'], 422],
]);

it('answers 503 and records nothing when PayPal cannot check the signature of a notice', function (?int $status): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order'], gateway: 'paypal');
    $before = webhookState($business);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => $status === null ? Http::failedConnection() : Http::response(['name' => 'INTERNAL_SERVER_ERROR'], $status),
    ]);
    $notice = paypalNotice('CHECKOUT.ORDER.APPROVED', ['id' => $payment->provider_reference, 'status' => 'APPROVED', 'purchase_units' => [[
        'custom_id' => $business->business->id.':'.$payment->id, 'amount' => ['currency_code' => 'USD', 'value' => '15.00'],
    ]]]);

    // The origin of the notice is not what failed, so PayPal must send it again later.
    postJson('/api/v1/webhooks/paypal', $notice, paypalSignature())->assertServiceUnavailable()->assertHeader('Retry-After', '30');

    expect(webhookState($business))->toEqual($before);
})->with([
    'its credentials refused' => [401],
    'an error of PayPal' => [500],
    'a lost connection' => [null],
]);

it('R26: confirms the payment and its order with a verified ONVO notice of the paid checkout', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()
        ->assertExactJson(['data' => ['evento' => 'checkout-session.succeeded:'.$payment->provider_reference, 'estado' => 'recibido']]);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500, 'balance' => 7500]);
    assertDatabaseHas('webhook_events', ['gateway' => 'onvo', 'event_id' => 'checkout-session.succeeded:'.$payment->provider_reference, 'event_type' => 'checkout-session.succeeded', 'status' => 'processed', 'error' => null]);
    expect(DB::table('webhook_events')->value('processed_at'))->not->toBeNull()
        ->and(json_decode((string) DB::table('webhook_events')->value('payload'), true)['data']['metadata']['paymentId'])->toBe($payment->id);
    // The notice only says something happened: the status comes from the server's own query.
    Http::assertSentCount(2);
});

it('discounts a verified balance payment from what a ready order still owes', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business, status: 'ready')['order'];
    $payment = StoredPayments::create($business, $order, purpose: 'balance');
    fakeOnvoCheckout($payment);

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'purpose' => 'balance', 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready', 'paid' => 15000, 'balance' => 0]);
});

it('finds the business by the provider reference when the notice brings no metadata', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);

    postOnvoNotice(onvoNotice('payment-intent.succeeded', ['id' => $payment->capture_reference, 'status' => 'succeeded', 'amount' => 750_000, 'metadata' => []]))
        ->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
});

it('leaves a failed or expired charge without confirming the order', function (string $intentStatus, string $paymentStatus): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment, $intentStatus, sessionStatus: 'expired');

    postOnvoNotice(onvoNotice('payment-intent.failed', ['id' => $payment->capture_reference, 'status' => $intentStatus, 'amount' => 750_000]))
        ->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => $paymentStatus]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
})->with([
    'failed' => ['failed', 'failed'],
    'expired' => ['canceled', 'expired'],
]);

it('R38: fails the charge with the notice ONVO documents for a failed intent, which brings no amount', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    $session = fn (string $status): array => ['id' => $payment->provider_reference, 'mode' => 'test', 'status' => $status, 'paymentIntentId' => $payment->capture_reference];
    $intent = fn (string $status, ?array $error): array => ['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750_000, 'currency' => 'CRC', 'status' => $status, 'lastPaymentError' => $error];
    // ONVO keeps a rejected intent waiting for another card; the server closes its checkout before failing the payment.
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference.'/expire' => Http::response($session('expired')),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference.'/cancel' => Http::response($intent('canceled', null)),
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::sequence()->push($session('open'))->push($session('expired')),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::sequence()
            ->push($intent('requires_payment_method', ['code' => 'card_declined']))->push($intent('canceled', null)),
    ]);

    // The example of payment-intent.failed in https://docs.onvopay.com/webhooks, with fictitious identifiers.
    postOnvoNotice(['type' => 'payment-intent.failed', 'data' => [
        'id' => $payment->capture_reference, 'accountId' => 'account-fictitious', 'currency' => 'CRC', 'capturableAmount' => 0, 'status' => 'requires_payment_method',
    ]])->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    assertDatabaseHas('webhook_events', ['event_id' => 'payment-intent.failed:'.$payment->capture_reference, 'status' => 'processed', 'error' => null]);
});

it('records a notice whose amount or currency differs from the payment as failed and confirms nothing', function (int $amountTotal, string $currency): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);

    postOnvoNotice(paidOnvoCheckout($business, $payment, $amountTotal, $currency))->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    assertDatabaseHas('webhook_events', ['event_id' => 'checkout-session.succeeded:'.$payment->provider_reference, 'status' => 'failed', 'error' => 'payments.event_amount_mismatch', 'processed_at' => null]);
    Http::assertNothingSent();
})->with([
    'a smaller amount' => [100, 'CRC'],
    'a fraction of a colón' => [750_050, 'CRC'],
    'another currency' => [750_000, 'USD'],
]);

it('still asks a paid intent for its amount, and a failed one for its currency and any amount it brings', function (string $type, array $charge): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);

    postOnvoNotice(['type' => $type, 'data' => ['id' => $payment->capture_reference, 'status' => 'requires_payment_method', ...$charge]])
        ->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    assertDatabaseHas('webhook_events', ['event_id' => $type.':'.$payment->capture_reference, 'status' => 'failed', 'error' => 'payments.event_amount_mismatch', 'processed_at' => null]);
    Http::assertNothingSent();
})->with([
    'a paid intent without amount' => ['payment-intent.succeeded', ['currency' => 'CRC', 'status' => 'succeeded']],
    'a failed intent with another amount' => ['payment-intent.failed', ['currency' => 'CRC', 'amount' => 100_000]],
    'a failed intent in another currency' => ['payment-intent.failed', ['currency' => 'USD']],
    'a failed intent without currency' => ['payment-intent.failed', []],
]);

it('captures an approved PayPal order and confirms the payment once the capture completes', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order, gateway: 'paypal');
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '15.00'];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'APPROVED', 'purchase_units' => [['amount' => $amount]]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference.'/capture' => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-fictitious-1', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]], 201),
    ]);

    postJson('/api/v1/webhooks/paypal', paypalNotice('CHECKOUT.ORDER.APPROVED', ['id' => $reference, 'status' => 'APPROVED', 'purchase_units' => [[
        'custom_id' => $business->business->id.':'.$payment->id, 'amount' => $amount,
    ]]]), paypalSignature())->assertOk()->assertExactJson(['data' => ['evento' => 'WH-FICTITIOUS-EVENT-1', 'estado' => 'recibido']]);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'capture_reference' => 'capture-fictitious-1']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    assertDatabaseHas('webhook_events', ['gateway' => 'paypal', 'event_id' => 'WH-FICTITIOUS-EVENT-1', 'event_type' => 'CHECKOUT.ORDER.APPROVED', 'status' => 'processed']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference.'/capture');
});

it('confirms a PayPal payment with the notice of its completed capture', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order, gateway: 'paypal');
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '15.00'];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-fictitious-2', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]]),
    ]);

    postJson('/api/v1/webhooks/paypal', paypalNotice('PAYMENT.CAPTURE.COMPLETED', [
        'id' => 'capture-fictitious-2', 'status' => 'COMPLETED', 'amount' => $amount, 'custom_id' => $business->business->id.':'.$payment->id,
        'supplementary_data' => ['related_ids' => ['order_id' => $reference]],
    ], 'WH-FICTITIOUS-EVENT-2'), paypalSignature())->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'capture_reference' => 'capture-fictitious-2']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
});

it('changes nothing when the notice names a payment that is no longer pending', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business, status: 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, status: 'succeeded');

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    Http::assertNothingSent();
});

it('records a notice that is not about a charge without asking the provider', function (): void {
    postOnvoNotice(onvoNotice('subscription.renewal.succeeded', ['id' => 'subscription-fictitious-1']))->assertOk()
        ->assertExactJson(['data' => ['evento' => 'subscription.renewal.succeeded:subscription-fictitious-1', 'estado' => 'recibido']]);

    assertDatabaseHas('webhook_events', ['gateway' => 'onvo', 'event_type' => 'subscription.renewal.succeeded', 'status' => 'processed']);
    Http::assertNothingSent();
});

it('records a notice about a charge no payment has as failed', function (): void {
    postOnvoNotice(onvoNotice('payment-intent.succeeded', ['id' => 'intent-unknown', 'status' => 'succeeded', 'amount' => 750_000]))
        ->assertOk()->assertJsonPath('data.estado', 'recibido');

    assertDatabaseHas('webhook_events', ['event_id' => 'payment-intent.succeeded:intent-unknown', 'status' => 'failed', 'error' => 'payments.not_found']);
    Http::assertNothingSent();
});

it('never applies a notice to a payment of another business', function (): void {
    $business = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    $otherPayment = StoredPayments::create($other, StoredOrders::create($other)['order']);
    $before = webhookState($business);
    $notice = paidOnvoCheckout($business, $payment);

    // The business of another store with this payment: the payment does not exist there.
    $notice['data']['metadata']['businessId'] = $other->business->id;
    postOnvoNotice($notice)->assertOk()->assertJsonPath('data.estado', 'recibido');
    // This payment with the checkout of a payment of another store.
    postOnvoNotice(onvoNotice('checkout-session.succeeded', [
        'id' => $otherPayment->provider_reference, 'amountTotal' => 750_000,
        'metadata' => ['businessId' => $business->business->id, 'paymentId' => $payment->id],
    ]))->assertOk()->assertJsonPath('data.estado', 'recibido');

    expect(webhookState($business)['payments'])->toEqual($before['payments'])
        ->and(webhookState($business)['orders'])->toEqual($before['orders']);
    assertDatabaseHas('webhook_events', ['event_id' => 'checkout-session.succeeded:'.$payment->provider_reference, 'error' => 'payments.not_found']);
    assertDatabaseHas('webhook_events', ['event_id' => 'checkout-session.succeeded:'.$otherPayment->provider_reference, 'error' => 'payments.event_reference_mismatch']);
    Http::assertNothingSent();
});

it('R38: applies an event sent twice only once and answers the repeated one without changing anything', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'recibido']]);
    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'repetido']]);

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500, 'balance' => 7500]);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    assertDatabaseCount('webhook_events', 1);
    // Only the first arrival asked the provider about the charge.
    Http::assertSentCount(2);
});

it('answers 503 and keeps nothing when the sync queue cannot ask the provider, and applies the notice sent again', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::sequence()
            ->push(['message' => 'unavailable'], 503)
            ->push(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    // The job ran inside the request and failed, so the notice was not kept either: the provider sends it again.
    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertServiceUnavailable()->assertHeader('Retry-After', '30')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/servicio-no-disponible');
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    assertDatabaseCount('webhook_events', 0);

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'recibido');
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    assertDatabaseCount('webhook_events', 1);
});

it('rejects a verified body that is not a notice without recording it', function (): void {
    postOnvoNotice(['data' => ['id' => 'session-fictitious']])->assertUnprocessable()
        ->assertJsonPath('detail', 'El aviso de la pasarela no tiene el formato esperado.');

    assertDatabaseCount('webhook_events', 0);
});

it('R38: answers at once and leaves the kept notice to the queue, whose worker applies it', function (): void {
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'recibido');

    // The request only kept the notice and its job: the provider has not been asked yet.
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'pending', 'error' => null, 'processed_at' => null]);
    expect(DB::table('jobs')->count())->toBe(1);
    Http::assertNothingSent();

    workOnePaymentEvent();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    expect(DB::table('jobs')->count())->toBe(0);
});

it('R38: leaves one job for an event sent twice', function (): void {
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'recibido');
    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'repetido');
    expect(DB::table('jobs')->count())->toBe(1);
    workOnePaymentEvent();
    workOnePaymentEvent();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    assertDatabaseCount('webhook_events', 1);
    Http::assertSentCount(2);
});

it('R46: never applies a kept notice twice, even when a second job processes it', function (): void {
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk();
    ProcessPaymentEventJob::dispatch((string) DB::table('webhook_events')->value('id'));
    expect(DB::table('jobs')->count())->toBe(2);
    workOnePaymentEvent();
    workOnePaymentEvent();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    expect(DB::table('jobs')->count())->toBe(0);
    // The second job found the notice processed and asked the provider nothing.
    Http::assertSentCount(2);
});

it('marks the notice failed while the provider cannot be asked and applies it once on a later attempt', function (): void {
    config(['queue.default' => 'database']);
    travelTo('2026-11-02 12:00:00');
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::sequence()
            ->push(['message' => 'unavailable'], 503)
            ->push(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk()->assertJsonPath('data.estado', 'recibido');
    workOnePaymentEvent();

    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'failed', 'error' => 'payments.unavailable']);
    expect(DB::table('jobs')->value('attempts'))->toBe(1)
        ->and(DB::table('jobs')->value('available_at'))->toBe(now()->addSeconds(10)->getTimestamp());

    travel(10)->seconds();
    workOnePaymentEvent();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'paid' => 7500]);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('leaves a notice with an error of its own failed, without trying again', function (): void {
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);

    postOnvoNotice(paidOnvoCheckout($business, $payment, amountTotal: 100))->assertOk();
    workOnePaymentEvent();

    assertDatabaseHas('webhook_events', ['event_id' => 'checkout-session.succeeded:'.$payment->provider_reference, 'status' => 'failed', 'error' => 'payments.event_amount_mismatch']);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    Http::assertNothingSent();
});

it('undoes everything when an unexpected error interrupts the notice, marks it failed and tries again later', function (): void {
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    fakeOnvoCheckout($payment);
    app()->bind(PaymentRepository::class, fn (): FailPaymentSave => new FailPaymentSave(app(EloquentPaymentRepository::class)));

    postOnvoNotice(paidOnvoCheckout($business, $payment))->assertOk();
    workOnePaymentEvent();

    // The order took the payment before saving the payment failed: the transaction undid both.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    assertDatabaseHas('webhook_events', ['event_id' => 'checkout-session.succeeded:'.$payment->provider_reference, 'status' => 'failed', 'error' => 'payments.event_not_processed']);
    expect(DB::table('jobs')->value('attempts'))->toBe(1);
});
