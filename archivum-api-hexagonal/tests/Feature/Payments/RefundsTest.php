<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Src\Payments\Payments\Application\DTOs\RefundPaymentCommand;
use Src\Payments\Payments\Application\UseCases\RefundPayment;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Payments\FailPaymentSave;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

pest()->use(RefreshDatabaseAsOwner::class);

// Refunds leave through the database queue of production: the operation that registers a refund only writes it and
// its job, and each test works the jobs as the worker does.
beforeEach(function (): void {
    config([
        'queue.default' => 'database',
        'payments.onvo.secret_key' => 'onvo_test_fake',
        'payments.paypal.client_id' => 'fake-client',
        'payments.paypal.client_secret' => 'fake-secret',
        'payments.paypal.webhook_id' => 'WH-FICTITIOUS',
    ]);
    Http::preventStrayRequests();
});

/** Run the next job of the database queue, as the worker of production does. */
function workNextRefundJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

/** The reconciliation the scheduler starts every few minutes. */
function reconcileRefundsNow(): PendingCommand
{
    $command = artisan('archivum:reconcile-payments');
    assert($command instanceof PendingCommand);

    return $command;
}

/**
 * What ONVO answers about a refund of a stored payment: its reference, the intent it returns, its cents and its state.
 *
 * @return array<string, mixed>
 */
function onvoRefundAnswer(PaymentModel $payment, string $reference, int $minor, string $status): array
{
    return ['id' => $reference, 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference, 'amount' => $minor, 'currency' => 'CRC', 'status' => $status];
}

/** Fake ONVO for one refund of a stored payment: the request answers its reference and the queries answer its state. */
function fakeOnvoRefund(PaymentModel $payment, string $reference, int $minor, string $status): void
{
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => $reference], 201),
        'api.onvopay.com/v1/refunds/'.$reference => Http::response(onvoRefundAnswer($payment, $reference, $minor, $status)),
    ]);
}

/**
 * What PayPal answers about a refund: its id, its state and its dollars.
 *
 * @return array<string, mixed>
 */
function paypalRefundAnswer(string $reference, string $status, string $value): array
{
    return ['id' => $reference, 'status' => $status, 'amount' => ['currency_code' => 'USD', 'value' => $value]];
}

/**
 * The notice PayPal sends when a refund completes: the refund, the custom_id Archivum sent with it and the capture it
 * returns in its link `up`.
 *
 * @return array<string, mixed>
 */
function paypalRefundNotice(string $reference, string $capture, string $value, ?string $customId, string $id = 'WH-FICTITIOUS-REFUND-1'): array
{
    return ['id' => $id, 'event_version' => '1.0', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => [
        ...paypalRefundAnswer($reference, 'COMPLETED', $value), 'custom_id' => $customId,
        'links' => [['rel' => 'up', 'method' => 'GET', 'href' => 'https://api.sandbox.paypal.com/v2/payments/captures/'.$capture]],
    ]];
}

/**
 * The headers PayPal signs a notice with; the values are fictitious and PayPal's verification API is faked.
 *
 * @return array<string, string>
 */
function paypalRefundSignature(): array
{
    return [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-fictitious',
        'PAYPAL-TRANSMISSION-ID' => 'transmission-refund-1', 'PAYPAL-TRANSMISSION-SIG' => 'fictitious-signature', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-02T12:00:00Z',
    ];
}

/**
 * An order waiting for the provider with two bottles of 15000 colones and their deposit of 15000 confirmed.
 *
 * @return array{order: OrderModel, lines: list<OrderLineModel>}
 */
function orderWithTwoOrderedBottles(StaffedBusiness $business): array
{
    $fixture = StoredOrders::create($business, 'ordered');
    app(BusinessTransaction::class)->scope($business->business->id);
    $fixture['order']->update(['subtotal' => 30000, 'due_now' => 15000, 'paid' => 15000, 'balance' => 15000]);
    $first = OrderLineModel::query()->where('order_id', $fixture['order']->id)->firstOrFail();
    $second = OrderLineModel::query()->create(['id' => (string) Str::uuid7(), 'business_id' => $business->business->id,
        'order_id' => $fixture['order']->id, 'kind' => 'bottle', 'perfume_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'unit_price' => 15000, 'status' => 'ordered']);

    return ['order' => $fixture['order'], 'lines' => [$first, $second]];
}

it('R30, R40: returns the deposit of a cancelled order through the queue and confirms it with the query of the server', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    fakeOnvoRefund($payment, 'refund-onvo-1', 750_000, 'succeeded');

    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk()->assertJsonPath('data.estado', 'cancelado')->assertJsonPath('data.pagado', 7500);

    // The cancellation only registered the refund and its job: the provider has not been asked yet.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending', 'attempted_at' => null, 'provider_reference' => null]);
    expect(DB::table('jobs')->count())->toBe(1);
    Http::assertNothingSent();

    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'refund-onvo-1', 'charged_amount' => 7500]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.onvopay.com/v1/refunds'
        && $request['paymentIntentId'] === $payment->capture_reference && $request['amount'] === 750_000);
    Http::assertSentCount(2);
});

it('R31, R40: returns each unavailable line, leaving the payment partially refunded and then refunded', function (): void {
    $business = actingAsOwner();
    ['order' => $order, 'lines' => [$first, $second]] = orderWithTwoOrderedBottles($business);
    $payment = StoredPayments::create($business, $order, 'succeeded', amount: 15000);
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::sequence()->push(['id' => 'refund-line-1'], 201)->push(['id' => 'refund-line-2'], 201),
        'api.onvopay.com/v1/refunds/refund-line-1' => Http::response(onvoRefundAnswer($payment, 'refund-line-1', 750_000, 'succeeded')),
        'api.onvopay.com/v1/refunds/refund-line-2' => Http::response(onvoRefundAnswer($payment, 'refund-line-2', 750_000, 'succeeded')),
    ]);
    $root = '/api/v1/panel/pedidos/'.$order->id.'/lineas/';

    postJson($root.$first->id.'/no-disponible')->assertOk()->assertJsonPath('data.estado', 'encargado');
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'order_line_id' => $first->id, 'amount' => 7500, 'status' => 'succeeded', 'provider_reference' => 'refund-line-1']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'partially_refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ordered', 'paid' => 7500]);

    postJson($root.$second->id.'/no-disponible')->assertOk()->assertJsonPath('data.estado', 'cancelado');
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'order_line_id' => $second->id, 'amount' => 7500, 'status' => 'succeeded', 'provider_reference' => 'refund-line-2']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 15000]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST')->map(fn (array $sent): mixed => $sent[0]['amount'])->values()->all())->toBe([750_000, 750_000]);
});

it('R40: rejects with a conflict a refund above what is left of its payment, counting the pending ones', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'partially_refunded');
    app(BusinessTransaction::class)->scope($business->business->id);
    DB::table('payments')->where('id', $payment->id)->update(['refunded_amount' => 2000]);
    DB::table('refunds')->insert([
        ['id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'payment_id' => $payment->id, 'amount' => 2000, 'status' => 'succeeded', 'charged_amount' => 2000, 'attempted_at' => now(), 'provider_reference' => 'refund-done'],
        ['id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'payment_id' => $payment->id, 'amount' => 3000, 'status' => 'pending', 'charged_amount' => null, 'attempted_at' => null, 'provider_reference' => null],
    ]);
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($business->business->id)));
    $refund = fn (int $amount): string => app(RefundPayment::class)->execute(new RefundPaymentCommand($payment->id, $amount));

    expect(fn (): string => $refund(2501))->toThrow(InvalidPaymentTransitionException::class, 'payments.invalid_refund');
    $id = $refund(2500);
    expect(fn (): string => $refund(1))->toThrow(InvalidPaymentTransitionException::class, 'payments.invalid_refund');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['id' => $id, 'payment_id' => $payment->id, 'amount' => 2500, 'status' => 'pending', 'attempted_at' => null]);
    expect((int) DB::table('refunds')->where('payment_id', $payment->id)->sum('amount'))->toBe(7500)
        ->and(DB::table('jobs')->count())->toBe(1);
    Http::assertNothingSent();
});

it('R40: leaves the refund failed, visible to the owner, and the payment unchanged when the gateway rejects it', function (?int $refusal, ?string $reference): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    if ($refusal !== null) {
        Http::fake(['api.onvopay.com/v1/refunds' => Http::response(['code' => 'refused'], $refusal)]);
    } else {
        fakeOnvoRefund($payment, 'refund-failed', 750_000, 'failed');
    }
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();

    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'failed', 'provider_reference' => $reference]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 7500]);
    actingAsOwner($business);
    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado', 'fallido')->assertJsonPath('data.0.monto', 7500)->assertJsonPath('data.0.pago', $payment->id);
    // A rejected refund is not sent again by itself.
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSentCount($refusal !== null ? 1 : 2);
})->with([
    'a declined refund' => [402, null],
    'a key ONVO does not accept' => [401, null],
    'an intent ONVO does not know' => [404, null],
    'a refund the provider failed' => [null, 'refund-failed'],
]);

it('R40: confirms with the reconciliation a refund the provider still processes', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-later'], 201),
        'api.onvopay.com/v1/refunds/refund-later' => Http::sequence()
            ->push(onvoRefundAnswer($payment, 'refund-later', 750_000, 'pending'))
            ->push(onvoRefundAnswer($payment, 'refund-later', 750_000, 'succeeded')),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'pending', 'provider_reference' => 'refund-later']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    // The reconciliation leaves a refund alone while it still waits for its confirmation.
    reconcileRefundsNow()->expectsOutputToContain('Ningún reembolso pendiente cambió de estado.')->assertSuccessful();
    Http::assertSentCount(2);

    travel(Payment::MINUTES_FOR_THE_NOTICE + 1)->minutes();
    reconcileRefundsNow()->expectsOutputToContain('Cambió de estado 1 reembolso pendiente.')->assertSuccessful();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'refund-later']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST')->count())->toBe(1);
    Http::assertSentCount(3);
});

it('R40: confirms a PayPal refund with its verified notice and applies the repeated notice once', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded', gateway: 'paypal');
    $capture = (string) $payment->capture_reference;
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/payments/captures/'.$capture.'/refund' => Http::response(paypalRefundAnswer('PAYPAL-REFUND-1', 'PENDING', '15.00'), 201),
        'api-m.sandbox.paypal.com/v2/payments/refunds/PAYPAL-REFUND-1' => Http::response(paypalRefundAnswer('PAYPAL-REFUND-1', 'COMPLETED', '15.00')),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    $refundId = (string) DB::table('refunds')->where('payment_id', $payment->id)->value('id');
    assertDatabaseHas('refunds', ['id' => $refundId, 'status' => 'pending', 'provider_reference' => 'PAYPAL-REFUND-1', 'charged_amount' => 1500]);
    $notice = paypalRefundNotice('PAYPAL-REFUND-1', $capture, '15.00', $business->business->id.':'.$refundId);

    postJson('/api/v1/webhooks/paypal', $notice, paypalRefundSignature())->assertOk()
        ->assertExactJson(['data' => ['evento' => 'WH-FICTITIOUS-REFUND-1', 'estado' => 'recibido']]);
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['id' => $refundId, 'status' => 'succeeded', 'provider_reference' => 'PAYPAL-REFUND-1']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    assertDatabaseHas('webhook_events', ['gateway' => 'paypal', 'event_id' => 'WH-FICTITIOUS-REFUND-1', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'status' => 'processed']);

    postJson('/api/v1/webhooks/paypal', $notice, paypalRefundSignature())->assertOk()->assertJsonPath('data.estado', 'repetido');

    expect(DB::table('jobs')->count())->toBe(0);
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0]);
    assertDatabaseCount('webhook_events', 1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/payments/captures/'.$capture.'/refund'
        && $request['amount'] === ['currency_code' => 'USD', 'value' => '15.00'] && $request['custom_id'] === $business->business->id.':'.$refundId
        && $request->header('PayPal-Request-Id') !== []);
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/refund'))->count())->toBe(1)
        ->and(Http::recorded(fn (Request $request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/payments/refunds/PAYPAL-REFUND-1')->count())->toBe(1);
});

it('R40: leaves failed a verified PayPal refund notice that does not match its refund, without applying it', function (string $value, ?string $otherCapture, string $error): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded', gateway: 'paypal');
    $capture = (string) $payment->capture_reference;
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/payments/captures/'.$capture.'/refund' => Http::response(paypalRefundAnswer('PAYPAL-REFUND-1', 'PENDING', '15.00'), 201),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    workNextRefundJob();
    app(BusinessTransaction::class)->scope($business->business->id);
    $refundId = (string) DB::table('refunds')->where('payment_id', $payment->id)->value('id');

    postJson('/api/v1/webhooks/paypal', paypalRefundNotice('PAYPAL-REFUND-1', $otherCapture ?? $capture, $value, $business->business->id.':'.$refundId), paypalRefundSignature())
        ->assertOk()->assertJsonPath('data.estado', 'recibido');
    workNextRefundJob();

    assertDatabaseHas('webhook_events', ['event_id' => 'WH-FICTITIOUS-REFUND-1', 'status' => 'failed', 'error' => $error]);
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['id' => $refundId, 'status' => 'pending', 'provider_reference' => 'PAYPAL-REFUND-1']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 7500]);
    expect(DB::table('jobs')->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v2/payments/refunds/'));
})->with([
    'other dollars' => ['14.99', null, 'payments.event_amount_mismatch'],
    'the capture of another payment' => ['15.00', 'capture-of-another-payment', 'payments.event_reference_mismatch'],
]);

it('R40: applies a PayPal notice that arrives before its refund kept the reference, without sending the refund again', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded', gateway: 'paypal');
    $capture = (string) $payment->capture_reference;
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/payments/refunds/PAYPAL-REFUND-EARLY' => Http::response(paypalRefundAnswer('PAYPAL-REFUND-EARLY', 'COMPLETED', '15.00')),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    // The request already left for PayPal, but its answer has not been kept yet: its job is still running elsewhere.
    app(BusinessTransaction::class)->scope($business->business->id);
    $refundId = (string) DB::table('refunds')->where('payment_id', $payment->id)->value('id');
    DB::table('refunds')->where('id', $refundId)->update(['charged_amount' => 1500, 'attempted_at' => now()]);
    DB::table('jobs')->delete();

    postJson('/api/v1/webhooks/paypal', paypalRefundNotice('PAYPAL-REFUND-EARLY', $capture, '15.00', $business->business->id.':'.$refundId), paypalRefundSignature())
        ->assertOk()->assertJsonPath('data.estado', 'recibido');
    workNextRefundJob();
    // Once the notice kept the reference, a job of the refund that runs after it asks PayPal nothing more.
    RequestRefundJob::dispatch($refundId, $business->business->id);
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['id' => $refundId, 'status' => 'succeeded', 'provider_reference' => 'PAYPAL-REFUND-EARLY']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    expect(DB::table('jobs')->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/refund'));
});

it('R40: never sends a refund twice, even when a second job runs it', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    fakeOnvoRefund($payment, 'refund-once', 750_000, 'pending');
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    app(BusinessTransaction::class)->scope($business->business->id);
    $refundId = (string) DB::table('refunds')->where('payment_id', $payment->id)->value('id');
    RequestRefundJob::dispatch($refundId, $business->business->id);
    expect(DB::table('jobs')->count())->toBe(2);

    workNextRefundJob();
    workNextRefundJob();

    // The second job found the refund already started and asked the provider nothing.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['id' => $refundId, 'status' => 'pending', 'provider_reference' => 'refund-once']);
    expect(DB::table('jobs')->count())->toBe(0);
    Http::assertSentCount(2);
});

it('R40: keeps an uncertain refund pending without sending it again or counting it as returned', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    Http::fake(['api.onvopay.com/v1/refunds' => Http::failedConnection()]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();

    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    $refundId = (string) DB::table('refunds')->where('payment_id', $payment->id)->value('id');
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'pending', 'provider_reference' => null, 'charged_amount' => 7500]);
    expect(DB::table('refunds')->where('payment_id', $payment->id)->value('attempted_at'))->not->toBeNull();
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 7500]);
    // Neither the queue, the owner's list nor the reconciliation sends it again: a person checks it in the provider.
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    actingAsOwner($business);
    postJson('/api/v1/panel/pedidos/'.$order->id.'/reembolsos')->assertOk()->assertJsonPath('data.0.estado', 'pendiente');
    travel(Payment::MINUTES_FOR_THE_NOTICE + 1)->minutes();
    reconcileRefundsNow()->expectsOutputToContain('Ningún reembolso pendiente cambió de estado.')->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(0);
    // Not even another job of the same refund sends it again, because ONVO could return the money twice.
    RequestRefundJob::dispatch($refundId, $business->business->id);
    workNextRefundJob();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSentCount(1);
});

it('R40: asks PayPal again through the queue for a refund whose answer was lost, with the same PayPal-Request-Id, and returns it once', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded', gateway: 'paypal');
    $capture = (string) $payment->capture_reference;
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/payments/captures/'.$capture.'/refund' => Http::sequence()
            ->pushFailedConnection()
            ->push(paypalRefundAnswer('PAYPAL-REFUND-1', 'COMPLETED', '15.00'), 201),
    ]);
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();

    workNextRefundJob();

    // The refund left without an answer: its job waits in the queue for its next attempt.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'pending', 'provider_reference' => null, 'charged_amount' => 1500]);
    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    travel(11)->seconds();
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'PAYPAL-REFUND-1', 'charged_amount' => 1500]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    expect(DB::table('jobs')->count())->toBe(0);
    // Both requests carry the same key, so PayPal answers the refund it may have created instead of a second one.
    $requests = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/refund'));
    expect($requests)->toHaveCount(2)
        ->and($requests->map(fn (array $sent): array => $sent[0]->header('PayPal-Request-Id'))->unique()->values()->all())->toHaveCount(1)
        ->and($requests->map(fn (array $sent): mixed => $sent[0]['amount']['value'])->values()->all())->toBe(['15.00', '15.00']);
});

it('R40: keeps the reference when applying the answer fails and confirms the refund with the reconciliation', function (): void {
    $business = actingAsCustomer();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    fakeOnvoRefund($payment, 'refund-durable', 750_000, 'succeeded');
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    app()->bind(PaymentRepository::class, fn (): FailPaymentSave => new FailPaymentSave(app(EloquentPaymentRepository::class), afterReference: true));

    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'pending', 'provider_reference' => 'refund-durable']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 7500]);
    expect(DB::table('jobs')->value('attempts'))->toBe(1);

    app()->bind(PaymentRepository::class, EloquentPaymentRepository::class);
    travel(Payment::MINUTES_FOR_THE_NOTICE + 1)->minutes();
    reconcileRefundsNow()->expectsOutputToContain('Cambió de estado 1 reembolso pendiente.')->assertSuccessful();
    // The job tries again, finds the refund already started and sends nothing.
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'refund-durable']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'paid' => 0]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST')->count())->toBe(1);
    Http::assertSentCount(3);
});

it('R31, R37: returns a PayPal payment in two refunds that add up to the cents it captured', function (): void {
    $business = actingAsOwner();
    ['order' => $order, 'lines' => [$first, $second]] = orderWithTwoOrderedBottles($business);
    $payment = StoredPayments::create($business, $order, 'succeeded', gateway: 'paypal', amount: 15000);
    app(BusinessTransaction::class)->scope($business->business->id);
    DB::table('payments')->where('id', $payment->id)->update(['exchange_rate' => '503.170000', 'charged_amount' => 2981]);
    $capture = (string) $payment->capture_reference;
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/payments/captures/'.$capture.'/refund' => Http::sequence()
            ->push(paypalRefundAnswer('PAYPAL-REFUND-A', 'COMPLETED', '14.91'), 201)
            ->push(paypalRefundAnswer('PAYPAL-REFUND-B', 'COMPLETED', '14.90'), 201),
    ]);
    $root = '/api/v1/panel/pedidos/'.$order->id.'/lineas/';

    postJson($root.$first->id.'/no-disponible')->assertOk();
    workNextRefundJob();
    postJson($root.$second->id.'/no-disponible')->assertOk()->assertJsonPath('data.estado', 'cancelado');
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['order_line_id' => $first->id, 'amount' => 7500, 'charged_amount' => 1491, 'status' => 'succeeded']);
    assertDatabaseHas('refunds', ['order_line_id' => $second->id, 'amount' => 7500, 'charged_amount' => 1490, 'status' => 'succeeded']);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 15000, 'charged_amount' => 2981]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/refund'))->map(fn (array $sent): mixed => $sent[0]['amount']['value'])->values()->all())
        ->toBe(['14.91', '14.90']);
});

it('changes nothing when the job of a refund names another business', function (): void {
    $business = actingAsCustomer();
    $other = StaffedBusiness::create();
    $order = StoredOrders::create($business, 'confirmed')['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    postJson('/api/v1/cuenta/pedidos/'.$order->id.'/cancelacion')->assertOk();
    app(BusinessTransaction::class)->scope($business->business->id);
    $refundId = (string) DB::table('refunds')->where('payment_id', $payment->id)->value('id');
    DB::table('jobs')->delete();

    RequestRefundJob::dispatch($refundId, $other->business->id);
    workNextRefundJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['id' => $refundId, 'status' => 'pending', 'attempted_at' => null, 'charged_amount' => null]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertNothingSent();
});
