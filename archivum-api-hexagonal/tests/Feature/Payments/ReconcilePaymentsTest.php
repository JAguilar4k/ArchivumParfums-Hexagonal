<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

// The payments of each test are created at 12:00; the reconciliation asks only for those that waited five minutes.
beforeEach(function (): void {
    config([
        'payments.onvo.secret_key' => 'onvo_test_fake',
        'payments.onvo.webhook_secret' => 'webhook_secret_fictitious',
        'payments.paypal.client_id' => 'fake-client',
        'payments.paypal.client_secret' => 'fake-secret',
    ]);
    travelTo('2026-11-02 12:00:00');
});

/** Run the reconciliation as the scheduler does. */
function reconcilePendingPayments(): PendingCommand
{
    $command = artisan('archivum:reconcile-payments');
    assert($command instanceof PendingCommand);

    return $command;
}

/**
 * What ONVO answers to the server's own query about the checkout of a stored payment, by address.
 *
 * @return array<string, PromiseInterface>
 */
function onvoStatusResponses(PaymentModel $payment, string $intentStatus = 'succeeded', string $sessionStatus = 'complete', int $amount = 750_000): array
{
    return [
        'https://api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => $sessionStatus, 'paymentIntentId' => $payment->capture_reference]),
        'https://api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => $amount, 'currency' => 'CRC', 'status' => $intentStatus]),
    ];
}

/** ONVO answering the server's own query about the checkout of a stored payment. */
function fakeOnvoStatusFor(PaymentModel $payment, string $intentStatus = 'succeeded', string $sessionStatus = 'complete', int $amount = 750_000): void
{
    Http::fake(onvoStatusResponses($payment, $intentStatus, $sessionStatus, $amount));
}

/**
 * Send the verified ONVO notice of the paid checkout of a stored payment, with the metadata Archivum sent with it.
 *
 * @return TestResponse<JsonResponse>
 */
function postPaidOnvoNoticeOf(StaffedBusiness $business, PaymentModel $payment): TestResponse
{
    return postJson('/api/v1/webhooks/onvo', ['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid',
        'amountTotal' => 750_000, 'currency' => 'CRC',
        'metadata' => ['businessId' => $business->business->id, 'paymentId' => $payment->id],
    ]], ['X-Webhook-Secret' => 'webhook_secret_fictitious']);
}

/**
 * The status of a payment and of its order, and what the order paid, read in the business of the payment.
 *
 * @return array{payment: mixed, order: mixed, paid: mixed}
 */
function reconciledState(StaffedBusiness $business, PaymentModel $payment): array
{
    app(BusinessTransaction::class)->scope($business->business->id);
    $order = DB::table('orders')->where('id', $payment->order_id)->first(['status', 'paid']);

    return ['payment' => DB::table('payments')->where('id', $payment->id)->value('status'), 'order' => $order?->status, 'paid' => $order?->paid];
}

it('R39: confirms a paid payment whose notice never arrived, as the notice would, and a second run changes nothing', function (): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order']);
    fakeOnvoStatusFor($payment);
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()->expectsOutputToContain('Cambió de estado 1 pago pendiente.')->assertSuccessful();

    expect(reconciledState($business, $payment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    Http::assertSentCount(2);

    reconcilePendingPayments()->expectsOutputToContain('Ningún pago pendiente cambió de estado.')->assertSuccessful();

    expect(reconciledState($business, $payment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    Http::assertSentCount(2);
});

it('asks nothing about a payment that is still waiting for its notice, has no reference of the provider or is no longer pending', function (): void {
    $business = StaffedBusiness::create();
    $settled = StoredPayments::create($business, StoredOrders::create($business, status: 'confirmed')['order'], status: 'succeeded');
    $withoutReference = StoredPayments::create($business, StoredOrders::create($business)['order']);
    app(BusinessTransaction::class)->run($business->business->id, fn () => PaymentModel::query()->whereKey($withoutReference->id)
        ->update(['provider_reference' => null, 'checkout_reference' => null, 'capture_reference' => null]));
    travelTo('2026-11-02 12:01:00');
    $recent = StoredPayments::create($business, StoredOrders::create($business)['order']);
    travelTo('2026-11-02 12:05:59');

    reconcilePendingPayments()->expectsOutputToContain('Ningún pago pendiente cambió de estado.')->assertSuccessful();

    Http::assertNothingSent();
    expect(reconciledState($business, $settled)['payment'])->toBe('succeeded')
        ->and(reconciledState($business, $withoutReference)['payment'])->toBe('pending')
        ->and(reconciledState($business, $recent)['payment'])->toBe('pending');
});

it('R39: leaves as it is a payment the webhook already confirmed, without asking the provider again', function (): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order']);
    fakeOnvoStatusFor($payment);
    postPaidOnvoNoticeOf($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()->expectsOutputToContain('Ningún pago pendiente cambió de estado.')->assertSuccessful();

    expect(reconciledState($business, $payment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    // Only the query of the notice: the reconciliation found no pending payment.
    Http::assertSentCount(2);
});

it('R39: applies nothing new when the notice arrives after the reconciliation', function (): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order']);
    fakeOnvoStatusFor($payment);
    travelTo('2026-11-02 12:05:00');
    reconcilePendingPayments()->assertSuccessful();

    postPaidOnvoNoticeOf($business, $payment)->assertOk()->assertJsonPath('data.estado', 'recibido');

    expect(reconciledState($business, $payment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    assertDatabaseHas('webhook_events', ['gateway' => 'onvo', 'status' => 'processed', 'error' => null]);
    // The notice found the payment closed and asked the provider nothing.
    Http::assertSentCount(2);
});

it('closes a charge the provider reports failed or expired, without confirming the order', function (string $intentStatus, string $paymentStatus): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order']);
    fakeOnvoStatusFor($payment, $intentStatus, sessionStatus: 'expired');
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()->expectsOutputToContain('Cambió de estado 1 pago pendiente.')->assertSuccessful();

    expect(reconciledState($business, $payment))->toBe(['payment' => $paymentStatus, 'order' => 'pending_payment', 'paid' => 0]);
})->with([
    'failed' => ['failed', 'failed'],
    'expired' => ['canceled', 'expired'],
]);

it('R39: keeps pending a payment whose provider does not answer, reviews the others and asks again in the next run', function (): void {
    Exceptions::fake();
    $business = StaffedBusiness::create();
    $unanswered = StoredPayments::create($business, StoredOrders::create($business)['order']);
    $answered = StoredPayments::create($business, StoredOrders::create($business)['order']);
    Http::fake([
        ...onvoStatusResponses($unanswered),
        ...onvoStatusResponses($answered),
        // The first query about this checkout finds the provider down; the next run finds it back.
        'https://api.onvopay.com/v1/checkout/sessions/'.$unanswered->provider_reference => Http::sequence()
            ->push(['message' => 'Servicio no disponible.'], 503)
            ->push(['id' => $unanswered->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $unanswered->capture_reference]),
    ]);
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()
        ->expectsOutputToContain('Cambió de estado 1 pago pendiente.')
        ->expectsOutputToContain('No se pudo revisar 1 pago; sigue pendiente para la siguiente corrida.')
        ->assertSuccessful();

    expect(reconciledState($business, $unanswered))->toBe(['payment' => 'pending', 'order' => 'pending_payment', 'paid' => 0])
        ->and(reconciledState($business, $answered))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    // A provider that does not answer is expected, so it is not reported as an error.
    Exceptions::assertNotReported(GatewayUnavailableException::class);

    travelTo('2026-11-02 12:10:00');
    reconcilePendingPayments()->expectsOutputToContain('Cambió de estado 1 pago pendiente.')->assertSuccessful();

    expect(reconciledState($business, $unanswered))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
});

it('keeps pending a payment the provider answers with another amount, reports it and still reviews the others', function (): void {
    Exceptions::fake();
    $business = StaffedBusiness::create();
    $mismatched = StoredPayments::create($business, StoredOrders::create($business)['order']);
    $answered = StoredPayments::create($business, StoredOrders::create($business)['order']);
    fakeOnvoStatusFor($mismatched, amount: 100);
    fakeOnvoStatusFor($answered);
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()
        ->expectsOutputToContain('Cambió de estado 1 pago pendiente.')
        ->expectsOutputToContain('No se pudo revisar 1 pago; sigue pendiente para la siguiente corrida.')
        ->assertSuccessful();

    expect(reconciledState($business, $mismatched))->toBe(['payment' => 'pending', 'order' => 'pending_payment', 'paid' => 0])
        ->and(reconciledState($business, $answered))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    Exceptions::assertReported(fn (InvalidValueException $exception): bool => $exception->translationKey() === 'payments.invalid_amount');
});

it('R39: reconciles the pending payments of two stores, each in a transaction of its own store', function (): void {
    $first = StaffedBusiness::create();
    $second = StaffedBusiness::create();
    $firstPayment = StoredPayments::create($first, StoredOrders::create($first)['order']);
    $secondPayment = StoredPayments::create($second, StoredOrders::create($second)['order']);
    $responses = [...onvoStatusResponses($firstPayment), ...onvoStatusResponses($secondPayment)];
    $pinned = [];
    Http::fake(function (Request $request) use ($responses, &$pinned): PromiseInterface {
        // The store that row security sees while the provider is asked about each charge.
        $pinned[$request->url()] = DB::scalar("select current_setting('app.business_id', true)");

        return $responses[$request->url()];
    });
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()->expectsOutputToContain('Cambiaron de estado 2 pagos pendientes.')->assertSuccessful();

    $expected = [
        'https://api.onvopay.com/v1/checkout/sessions/'.$firstPayment->provider_reference => $first->business->id,
        'https://api.onvopay.com/v1/payment-intents/'.$firstPayment->capture_reference => $first->business->id,
        'https://api.onvopay.com/v1/checkout/sessions/'.$secondPayment->provider_reference => $second->business->id,
        'https://api.onvopay.com/v1/payment-intents/'.$secondPayment->capture_reference => $second->business->id,
    ];
    ksort($expected);
    ksort($pinned);

    expect(reconciledState($first, $firstPayment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500])
        ->and(reconciledState($second, $secondPayment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500])
        ->and($pinned)->toBe($expected);
});

it('captures an approved PayPal order in the query and confirms its payment (#91)', function (): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order'], gateway: 'paypal');
    $reference = (string) $payment->provider_reference;
    $amount = ['currency_code' => 'USD', 'value' => '15.00'];
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference => Http::response(['id' => $reference, 'status' => 'APPROVED', 'purchase_units' => [['amount' => $amount]]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference.'/capture' => Http::response(['id' => $reference, 'status' => 'COMPLETED', 'purchase_units' => [[
            'amount' => $amount, 'payments' => ['captures' => [['id' => 'capture-fictitious-reconciled', 'status' => 'COMPLETED', 'amount' => $amount]]],
        ]]], 201),
    ]);
    travelTo('2026-11-02 12:05:00');

    reconcilePendingPayments()->expectsOutputToContain('Cambió de estado 1 pago pendiente.')->assertSuccessful();

    expect(reconciledState($business, $payment))->toBe(['payment' => 'succeeded', 'order' => 'confirmed', 'paid' => 7500]);
    assertDatabaseHas('payments', ['id' => $payment->id, 'capture_reference' => 'capture-fictitious-reconciled']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/'.$reference.'/capture');
});

it('R39: runs every five minutes and never twice at the same time', function (): void {
    $events = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (Event $event): bool => str_contains((string) $event->command, 'archivum:reconcile-payments'),
    ));

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('*/5 * * * *')
        ->and($events[0]->withoutOverlapping)->toBeTrue()
        ->and($events[0]->expiresAt)->toBe(15);

    // While a run holds the lock, the scheduler starts no other one.
    travelTo('2026-11-02 12:10:00');
    $events[0]->mutex->create($events[0]);
    $scheduler = artisan('schedule:run');
    assert($scheduler instanceof PendingCommand);
    // run() runs it now, while the lock is still held.
    $scheduler->expectsOutputToContain('No scheduled commands are ready to run.')->assertSuccessful()->run();
    $events[0]->mutex->forget($events[0]);
});
