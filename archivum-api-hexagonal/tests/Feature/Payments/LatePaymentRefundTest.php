<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

pest()->use(RefreshDatabaseAsOwner::class);

// A charge the provider confirms after the customer cancelled the order (#192). The customer cancels with the endpoint
// of #83 and each notice reaches the webhook of #94 as the provider sends it, with no account signed in. The notices
// use the sync queue, so each one is applied inside its request; only the job that asks the provider for a refund is
// faked, except in the test that follows the refund until the provider confirms it.
beforeEach(function (): void {
    config([
        'payments.onvo.secret_key' => 'onvo_test_fake',
        'payments.onvo.webhook_secret' => 'webhook_secret_fictitious',
        'payments.paypal.client_id' => 'fake-client',
        'payments.paypal.client_secret' => 'fake-secret',
        'payments.paypal.webhook_id' => 'WH-FICTITIOUS',
    ]);
    Http::preventStrayRequests();
});

/** Cancel the order as its customer, and then sign the customer out: the provider's request has no account. */
function cancelBeforeTheLateCharge(string $orderId): void
{
    postJson('/api/v1/cuenta/pedidos/'.$orderId.'/cancelacion')->assertOk()->assertJsonPath('data.estado', 'cancelado');
    Auth::forgetGuards();
}

/** ONVO answering the server's own query about a stored payment: its checkout is complete and its intent succeeded. */
function fakeLateOnvoCharge(PaymentModel $payment): void
{
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => $payment->charged_amount * 100, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);
}

/**
 * Send to the ONVO webhook the notice of the paid checkout of a stored payment, with the secret ONVO sends.
 *
 * @return TestResponse<JsonResponse>
 */
function sendLateOnvoNotice(StaffedBusiness $business, PaymentModel $payment): TestResponse
{
    return postJson('/api/v1/webhooks/onvo', ['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid',
        'amountTotal' => $payment->charged_amount * 100, 'currency' => 'CRC',
        'metadata' => ['businessId' => $business->business->id, 'paymentId' => $payment->id],
    ]], ['X-Webhook-Secret' => 'webhook_secret_fictitious']);
}

/** The reconciliation of #96, once the stored payments waited long enough for their notice. */
function reconcileAfterTheLateCharge(): void
{
    travel(Payment::MINUTES_FOR_THE_NOTICE)->minutes();
    $command = artisan('archivum:reconcile-payments');
    assert($command instanceof PendingCommand);
    $command->assertSuccessful();
}

/** Run one job of the database queue, as the worker of production does. */
function workLateChargeJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

/**
 * Every row of a store that a late charge could touch, as that store sees them.
 *
 * @return array<string, array<int, stdClass>>
 */
function lateChargeRowsOf(StaffedBusiness $business): array
{
    return app(BusinessTransaction::class)->run($business->business->id, fn (): array => [
        'orders' => DB::table('orders')->orderBy('id')->get()->all(),
        'order_lines' => DB::table('order_lines')->orderBy('id')->get()->all(),
        'decant_bottles' => DB::table('decant_bottles')->orderBy('id')->get()->all(),
        'payments' => DB::table('payments')->orderBy('id')->get()->all(),
        'refunds' => DB::table('refunds')->orderBy('id')->get()->all(),
        'audit_logs' => DB::table('audit_logs')->orderBy('id')->get()->all(),
    ]);
}

it('R38, R40: keeps a charge ONVO confirms after the cancellation and registers its full refund in the store of the payment', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $fixture = StoredOrders::create($business, withDecant: true);
    $order = $fixture['order'];
    $payment = StoredPayments::create($business, $order, amount: 8300);
    $other = StaffedBusiness::create();
    $otherOrder = StoredOrders::create($other, 'cancelled', withDecant: true)['order'];
    StoredPayments::create($other, $otherOrder, amount: 8300);
    cancelBeforeTheLateCharge($order->id);
    $otherRows = lateChargeRowsOf($other);
    fakeLateOnvoCharge($payment);

    $response = sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    // The payment matches the provider and the order stays cancelled, without its ml back. Its paid amount counts the
    // charge until the refund is confirmed, as the specification says for a charge confirmed after the cancellation.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 8300]);
    assertDatabaseHas('decant_bottles', ['id' => $fixture['bottle']?->id, 'remaining_ml' => 100, 'reserved_ml' => 0]);
    // The same transaction registered the refund of the whole charge, tied to its payment, and left its job queued.
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 8300, 'status' => 'pending', 'attempted_at' => null, 'provider_reference' => null]);
    assertDatabaseCount('refunds', 1);
    $refundId = (string) DB::table('refunds')->value('id');
    Queue::assertPushed(RequestRefundJob::class, 1);
    Queue::assertPushed(RequestRefundJob::class, fn (RequestRefundJob $job): bool => $job->refundId === $refundId && $job->businessId === $business->business->id);
    // The trail of #113 names the system as its author: the notice of the provider brings no account.
    $entry = DB::table('audit_logs')->where('action', AuditAction::RefundRequested->value)->sole();
    expect($entry->resource_id)->toBe($refundId)
        ->and($entry->actor_user_id)->toBeNull()
        ->and($entry->request_id)->toBe($response->headers->get(AssignRequestId::HEADER))
        ->and(json_decode((string) $entry->after, true))->toMatchArray(['payment_id' => $payment->id, 'order_id' => $order->id, 'amount' => 8300]);
    // The other store neither sees the refund nor changes.
    expect(lateChargeRowsOf($other))->toEqual($otherRows);
    Http::assertSentCount(2);
});

it('R38, R39: leaves one refund when the late notice arrives twice and the reconciliation runs after it', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    cancelBeforeTheLateCharge($order->id);
    fakeLateOnvoCharge($payment);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    sendLateOnvoNotice($business, $payment)->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'recibido']]);
    sendLateOnvoNotice($business, $payment)->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'repetido']]);
    reconcileAfterTheLateCharge();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending']);
    assertDatabaseCount('refunds', 1);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    assertDatabaseCount('webhook_events', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    // Only the first notice asked the provider: the reconciliation found no payment waiting for it.
    Http::assertSentCount(2);
});

it('R38, R39: leaves one refund when the reconciliation confirms the late charge before its notice arrives', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    cancelBeforeTheLateCharge($order->id);
    fakeLateOnvoCharge($payment);

    reconcileAfterTheLateCharge();
    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending']);
    assertDatabaseCount('refunds', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    // The notice found the payment confirmed and asked the provider nothing.
    Http::assertSentCount(2);
});

it('R38, R40: keeps a PayPal capture that completes after the cancellation and registers its full refund without capturing again', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order, gateway: 'paypal');
    cancelBeforeTheLateCharge($order->id);
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '15.00'];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-fictitious-late', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]]),
    ]);

    // The values of the signature are fictitious and PayPal's verification API is faked.
    postJson('/api/v1/webhooks/paypal', ['id' => 'WH-FICTITIOUS-LATE-1', 'event_version' => '1.0', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => [
        'id' => 'capture-fictitious-late', 'status' => 'COMPLETED', 'amount' => $amount, 'custom_id' => $business->business->id.':'.$payment->id,
        'supplementary_data' => ['related_ids' => ['order_id' => $reference]],
    ]], [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-fictitious',
        'PAYPAL-TRANSMISSION-ID' => 'transmission-late-1', 'PAYPAL-TRANSMISSION-SIG' => 'fictitious-signature', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-07T12:00:00Z',
    ])->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'capture_reference' => 'capture-fictitious-late']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending', 'attempted_at' => null]);
    assertDatabaseCount('refunds', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
});

it('R40: returns the late charge once ONVO confirms its refund, and the order keeps what it paid before the charge', function (): void {
    // Here the notice and the refund leave through the database queue of production, worked as the worker does.
    config(['queue.default' => 'database']);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    cancelBeforeTheLateCharge($order->id);
    fakeLateOnvoCharge($payment);
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-late-1'], 201),
        'api.onvopay.com/v1/refunds/refund-late-1' => Http::response(['id' => 'refund-late-1', 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference, 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');
    workLateChargeJob();

    // The notice registered the refund and left its request to the provider in the queue.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending', 'attempted_at' => null]);
    expect(DB::table('jobs')->count())->toBe(1);
    Http::assertSentCount(2);

    workLateChargeJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'refund-late-1', 'charged_amount' => 7500]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'paid' => 0]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.onvopay.com/v1/refunds'
        && $request['paymentIntentId'] === $payment->capture_reference && $request['amount'] === 750_000);
    Http::assertSentCount(4);
});
