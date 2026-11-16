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
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Decants\Packs\Infrastructure\Jobs\ExpireDecantPackJob;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Sales\Orders\Infrastructure\Jobs\ExpireUnpaidOrderJob;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

pest()->use(RefreshDatabaseAsOwner::class);

// A charge the provider confirms after the customer cancelled the order, after it expired unpaid, or after a decant
// pack expired unpaid (#192). The customer cancels with the endpoint of #83, the order expires with the job of #158 and
// the pack with the job of #159, and each notice reaches the webhook of #94 as the provider sends it, with no account
// signed in. The notices use the sync queue, so each one is applied inside its request; only the job that asks the
// provider for a refund is faked, except in the tests that follow the refund until the provider confirms it.
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

/**
 * Let the 30 minutes of the order pass and run its expiry as the worker does (#158): the order expires, its decant
 * gives its ml back and its pending payment expires with it. Then sign the customer out.
 */
function expireBeforeTheLateCharge(StaffedBusiness $business, OrderModel $order): void
{
    travel(31)->minutes();
    ExpireUnpaidOrderJob::dispatchSync($order->id, $business->business->id);
    $states = app(BusinessTransaction::class)->run($business->business->id, fn (): array => [
        DB::table('orders')->where('id', $order->id)->value('status'),
        DB::table('payments')->where('order_id', $order->id)->pluck('status')->all(),
    ]);
    expect($states)->toBe(['expired', ['expired']]);
    Auth::forgetGuards();
}

/**
 * Let the 30 minutes to pay a decant pack pass and run its expiry as the worker does (#159): the pack expires and its
 * pending payment expires with it.
 */
function expirePackBeforeTheLateCharge(StaffedBusiness $business, string $packId): void
{
    travel(31)->minutes();
    ExpireDecantPackJob::dispatchSync($packId, $business->business->id, ExpiryReason::Unpaid);
    $states = app(BusinessTransaction::class)->run($business->business->id, fn (): array => [
        DB::table('decant_packs')->where('id', $packId)->value('status'),
        DB::table('payments')->where('decant_pack_id', $packId)->pluck('status')->all(),
    ]);
    expect($states)->toBe(['expired', ['expired']]);
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
    runTheLateChargeReconciliation();
}

/** Run the reconciliation of #96 at this moment, as the scheduler does. */
function runTheLateChargeReconciliation(): void
{
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
        'decant_packs' => DB::table('decant_packs')->orderBy('id')->get()->all(),
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

it('R38, R40: keeps a charge ONVO confirms after the order expired, though the expiry closed its payment, and registers its full refund in the store of the payment', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $fixture = StoredOrders::create($business, withDecant: true);
    $order = $fixture['order'];
    $payment = StoredPayments::create($business, $order, amount: 8300);
    $other = StaffedBusiness::create();
    $otherOrder = StoredOrders::create($other, 'expired', withDecant: true)['order'];
    StoredPayments::create($other, $otherOrder, 'expired', amount: 8300);
    expireBeforeTheLateCharge($business, $order);
    $otherRows = lateChargeRowsOf($other);
    fakeLateOnvoCharge($payment);

    $response = sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    // The payment leaves expired because ONVO charged it, and the order stays expired, without its ml back. Its paid
    // amount counts the charge until the refund is confirmed, as for a charge confirmed after a cancellation.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0, 'capture_reference' => $payment->capture_reference]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired', 'paid' => 8300]);
    assertDatabaseHas('order_lines', ['order_id' => $order->id, 'kind' => 'decant', 'status' => 'released']);
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
    // The other store, whose expired order has an expired payment too, neither sees the refund nor changes.
    expect(lateChargeRowsOf($other))->toEqual($otherRows);
    Http::assertSentCount(2);
});

it('R38, R39: leaves one refund when the late notice of an expired order arrives twice and the reconciliation runs after it', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    expireBeforeTheLateCharge($business, $order);
    fakeLateOnvoCharge($payment);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    sendLateOnvoNotice($business, $payment)->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'recibido']]);
    sendLateOnvoNotice($business, $payment)->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'repetido']]);
    reconcileAfterTheLateCharge();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired', 'paid' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending']);
    assertDatabaseCount('refunds', 1);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    assertDatabaseCount('webhook_events', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    // Only the first notice asked the provider: the reconciliation found the payment confirmed.
    Http::assertSentCount(2);
});

it('R38, R40: keeps a PayPal capture completed before the expiry whose notice arrives after it, and registers its full refund without capturing again', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order, gateway: 'paypal');
    expireBeforeTheLateCharge($business, $order);
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '15.00'];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-fictitious-expired', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]]),
    ]);

    // The values of the signature are fictitious and PayPal's verification API is faked.
    postJson('/api/v1/webhooks/paypal', ['id' => 'WH-FICTITIOUS-EXPIRED-1', 'event_version' => '1.0', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => [
        'id' => 'capture-fictitious-expired', 'status' => 'COMPLETED', 'amount' => $amount, 'custom_id' => $business->business->id.':'.$payment->id,
        'supplementary_data' => ['related_ids' => ['order_id' => $reference]],
    ]], [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-fictitious',
        'PAYPAL-TRANSMISSION-ID' => 'transmission-expired-1', 'PAYPAL-TRANSMISSION-SIG' => 'fictitious-signature', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-07T12:31:00Z',
    ])->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'capture_reference' => 'capture-fictitious-expired']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired', 'paid' => 7500]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending', 'attempted_at' => null]);
    assertDatabaseCount('refunds', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
});

it('R40: returns the late charge of an expired order once ONVO confirms its refund, and the payment ends refunded', function (): void {
    // Here the notice and the refund leave through the database queue of production, worked as the worker does.
    config(['queue.default' => 'database']);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    expireBeforeTheLateCharge($business, $order);
    fakeLateOnvoCharge($payment);
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-expired-1'], 201),
        'api.onvopay.com/v1/refunds/refund-expired-1' => Http::response(['id' => 'refund-expired-1', 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference, 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');
    workLateChargeJob();
    workLateChargeJob();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'refund-expired-1', 'charged_amount' => 7500]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 7500]);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired', 'paid' => 0]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.onvopay.com/v1/refunds'
        && $request['paymentIntentId'] === $payment->capture_reference && $request['amount'] === 750_000);
});

it('R38, R39: leaves one refund when the reconciliation finds the late charge of an expired order before its notice arrives', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    expireBeforeTheLateCharge($business, $order);
    fakeLateOnvoCharge($payment);

    // The webhook never told Archivum, but the reconciliation asks ONVO about the payment the expiry closed.
    reconcileAfterTheLateCharge();

    $rows = fn (): array => app(BusinessTransaction::class)->run($business->business->id, fn (): array => [
        'payment' => DB::table('payments')->where('id', $payment->id)->value('status'),
        'paid' => DB::table('orders')->where('id', $order->id)->value('paid'),
        'refunds' => DB::table('refunds')->get(['payment_id', 'amount', 'status'])->map(fn (object $refund): array => (array) $refund)->all(),
    ]);
    $reconciled = ['payment' => 'succeeded', 'paid' => 7500, 'refunds' => [['payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending']]];
    expect($rows())->toBe($reconciled);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertSentCount(2);

    // The notice that arrives afterwards finds the payment confirmed and asks the provider nothing.
    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    expect($rows())->toBe($reconciled);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertSentCount(2);
});

it('R39: asks about the payment the expiry closed for 24 hours, and it stays expired while ONVO reports its checkout expired', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order);
    expireBeforeTheLateCharge($business, $order);
    Http::fake(['api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'expired'])]);

    // Right after the expiry its notice may still arrive: the reconciliation waits for it, as for a pending payment.
    runTheLateChargeReconciliation();
    Http::assertNothingSent();

    // Five minutes after the expiry, ONVO answers that nobody paid the checkout: nothing changes.
    reconcileAfterTheLateCharge();
    Http::assertSentCount(1);

    // A day after the expiry, the reconciliation no longer asks about it.
    travel(Payment::HOURS_FOR_A_LATE_CHARGE)->hours();
    runTheLateChargeReconciliation();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'expired']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired', 'paid' => 0]);
    assertDatabaseCount('refunds', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
});

it('asks nothing about an expired payment of an order that still waits for its payment, and changes nothing', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = actingAsCustomer();
    $order = StoredOrders::create($business)['order'];
    // ONVO itself reported this checkout expired; the order keeps waiting for another payment.
    $payment = StoredPayments::create($business, $order, 'expired');
    Auth::forgetGuards();
    fakeLateOnvoCharge($payment);

    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'expired']);
    assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment', 'paid' => 0]);
    assertDatabaseCount('refunds', 0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('R38, R40: keeps a charge ONVO confirms after the pack expired unpaid, though the expiry closed its payment, and registers its full refund in the store of the payment', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack);
    $other = StaffedBusiness::create();
    $otherPack = StoredPacks::create($other, ['status' => 'expired', 'expires_at' => now()])['pack_id'];
    StoredPayments::forPack($other, $otherPack, 'expired');
    expirePackBeforeTheLateCharge($business, $pack);
    $otherRows = lateChargeRowsOf($other);
    fakeLateOnvoCharge($payment);

    $response = sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    // The payment leaves expired because ONVO charged it, and the pack stays expired with its five decants: a charge
    // that arrives after the expiry never activates the pack (precisions of R32–R35).
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'refunded_amount' => 0, 'capture_reference' => $payment->capture_reference]);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'expired', 'remaining_decants' => 5]);
    // The same transaction registered the refund of the whole charge, tied to its payment, and left its job queued.
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 40000, 'status' => 'pending', 'attempted_at' => null, 'provider_reference' => null, 'order_line_id' => null]);
    assertDatabaseCount('refunds', 1);
    $refundId = (string) DB::table('refunds')->value('id');
    Queue::assertPushed(RequestRefundJob::class, 1);
    Queue::assertPushed(RequestRefundJob::class, fn (RequestRefundJob $job): bool => $job->refundId === $refundId && $job->businessId === $business->business->id);
    // The trail of #113 names the system as its author, and the pack the payment paid.
    $entry = DB::table('audit_logs')->where('action', AuditAction::RefundRequested->value)->sole();
    expect($entry->resource_id)->toBe($refundId)
        ->and($entry->actor_user_id)->toBeNull()
        ->and($entry->request_id)->toBe($response->headers->get(AssignRequestId::HEADER))
        ->and(json_decode((string) $entry->after, true))->toEqual(['payment_id' => $payment->id, 'decant_pack_id' => $pack, 'order_line_id' => null, 'amount' => 40000]);
    // The other store, whose expired pack has an expired payment too, neither sees the refund nor changes.
    expect(lateChargeRowsOf($other))->toEqual($otherRows);
    Http::assertSentCount(2);
});

it('R38, R39: leaves one refund when the late notice of an expired pack arrives twice and the reconciliation runs after it', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack);
    expirePackBeforeTheLateCharge($business, $pack);
    fakeLateOnvoCharge($payment);
    $eventId = 'checkout-session.succeeded:'.$payment->provider_reference;

    sendLateOnvoNotice($business, $payment)->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'recibido']]);
    sendLateOnvoNotice($business, $payment)->assertOk()->assertExactJson(['data' => ['evento' => $eventId, 'estado' => 'repetido']]);
    reconcileAfterTheLateCharge();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'expired', 'remaining_decants' => 5]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 40000, 'status' => 'pending']);
    assertDatabaseCount('refunds', 1);
    assertDatabaseHas('webhook_events', ['event_id' => $eventId, 'status' => 'processed', 'error' => null]);
    assertDatabaseCount('webhook_events', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    // Only the first notice asked the provider: the reconciliation found the payment confirmed.
    Http::assertSentCount(2);
});

it('R38, R40: keeps a PayPal capture completed before the expiry of the pack whose notice arrives after it, and registers its full refund without capturing again', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack, gateway: 'paypal');
    expirePackBeforeTheLateCharge($business, $pack);
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '80.00'];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-fictitious-pack', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]]),
    ]);

    // The values of the signature are fictitious and PayPal's verification API is faked.
    postJson('/api/v1/webhooks/paypal', ['id' => 'WH-FICTITIOUS-PACK-1', 'event_version' => '1.0', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => [
        'id' => 'capture-fictitious-pack', 'status' => 'COMPLETED', 'amount' => $amount, 'custom_id' => $business->business->id.':'.$payment->id,
        'supplementary_data' => ['related_ids' => ['order_id' => $reference]],
    ]], [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-fictitious',
        'PAYPAL-TRANSMISSION-ID' => 'transmission-pack-1', 'PAYPAL-TRANSMISSION-SIG' => 'fictitious-signature', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-10T15:31:00Z',
    ])->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded', 'capture_reference' => 'capture-fictitious-pack']);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'expired', 'remaining_decants' => 5]);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 40000, 'status' => 'pending', 'attempted_at' => null]);
    assertDatabaseCount('refunds', 1);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
});

it('R39: never captures a PayPal order the customer approves after the pack expired, and its payment stays expired', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack, gateway: 'paypal');
    expirePackBeforeTheLateCharge($business, $pack);
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '80.00'];
    // PayPal would complete a capture if the server asked for one.
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'APPROVED', 'purchase_units' => [['amount' => $amount]]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference.'/capture' => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-never-requested', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]], 201),
    ]);

    // The reconciliation asks about the payment the expiry closed, but an approval alone is not a charge.
    reconcileAfterTheLateCharge();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'expired', 'capture_reference' => $payment->capture_reference]);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'expired', 'remaining_decants' => 5]);
    assertDatabaseCount('refunds', 0);
    Queue::assertNothingPushed();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
    Http::assertSentCount(2);
});

it('R40: returns the late charge of an expired pack once ONVO confirms its refund, and the payment ends refunded while the pack stays expired', function (): void {
    // Here the notice and the refund leave through the database queue of production, worked as the worker does.
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack);
    expirePackBeforeTheLateCharge($business, $pack);
    fakeLateOnvoCharge($payment);
    Http::fake([
        'api.onvopay.com/v1/refunds' => Http::response(['id' => 'refund-pack-1'], 201),
        'api.onvopay.com/v1/refunds/refund-pack-1' => Http::response(['id' => 'refund-pack-1', 'mode' => 'test', 'paymentIntentId' => $payment->capture_reference, 'amount' => 4_000_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');
    workLateChargeJob();
    workLateChargeJob();

    // The refund locked the pack before the payment, as every operation on the payment of a pack does, and returned
    // the whole charge; a pack keeps no paid amount, so it stays as the expiry left it.
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'status' => 'succeeded', 'provider_reference' => 'refund-pack-1', 'charged_amount' => 40000]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded', 'refunded_amount' => 40000]);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'expired', 'remaining_decants' => 5]);
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.onvopay.com/v1/refunds'
        && $request['paymentIntentId'] === $payment->capture_reference && $request['amount'] === 4_000_000);
});

it('R38, R39: leaves one refund when the reconciliation finds the late charge of an expired pack before its notice arrives', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack);
    expirePackBeforeTheLateCharge($business, $pack);
    fakeLateOnvoCharge($payment);

    // The webhook never told Archivum, but the reconciliation asks ONVO about the payment the expiry of the pack closed.
    reconcileAfterTheLateCharge();

    $rows = fn (): array => app(BusinessTransaction::class)->run($business->business->id, fn (): array => [
        'payment' => DB::table('payments')->where('id', $payment->id)->value('status'),
        'pack' => DB::table('decant_packs')->where('id', $pack)->value('status'),
        'refunds' => DB::table('refunds')->get(['payment_id', 'amount', 'status'])->map(fn (object $refund): array => (array) $refund)->all(),
    ]);
    $reconciled = ['payment' => 'succeeded', 'pack' => 'expired', 'refunds' => [['payment_id' => $payment->id, 'amount' => 40000, 'status' => 'pending']]];
    expect($rows())->toBe($reconciled);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertSentCount(2);

    // The notice that arrives afterwards finds the payment confirmed and asks the provider nothing.
    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    expect($rows())->toBe($reconciled);
    Queue::assertPushed(RequestRefundJob::class, 1);
    Http::assertSentCount(2);
});

it('R39: asks about the payment the expiry of the pack closed for 24 hours, and it stays expired while ONVO reports its checkout expired', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    $payment = StoredPayments::forPack($business, $pack);
    expirePackBeforeTheLateCharge($business, $pack);
    Http::fake(['api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'expired'])]);

    // Right after the expiry its notice may still arrive: the reconciliation waits for it, as for a pending payment.
    runTheLateChargeReconciliation();
    Http::assertNothingSent();

    // Five minutes after the expiry, ONVO answers that nobody paid the checkout: nothing changes.
    reconcileAfterTheLateCharge();
    Http::assertSentCount(1);

    // A day after the expiry, the reconciliation no longer asks about it.
    travel(Payment::HOURS_FOR_A_LATE_CHARGE)->hours();
    runTheLateChargeReconciliation();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'expired']);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'expired', 'remaining_decants' => 5]);
    assertDatabaseCount('refunds', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
});

it('asks nothing about an expired payment of a pack that still waits for its payment, and changes nothing', function (): void {
    Queue::fake([RequestRefundJob::class]);
    $business = StaffedBusiness::create();
    $pack = StoredPacks::create($business)['pack_id'];
    // ONVO itself reported this checkout expired; the pack keeps waiting for another payment.
    $payment = StoredPayments::forPack($business, $pack, 'expired');
    fakeLateOnvoCharge($payment);

    sendLateOnvoNotice($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');
    reconcileAfterTheLateCharge();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'expired']);
    assertDatabaseHas('decant_packs', ['id' => $pack, 'status' => 'pending_payment', 'remaining_decants' => 5]);
    assertDatabaseCount('refunds', 0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
