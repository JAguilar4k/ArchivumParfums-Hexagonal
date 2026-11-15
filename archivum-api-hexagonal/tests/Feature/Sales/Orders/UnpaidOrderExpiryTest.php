<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Sales\Orders\Infrastructure\Jobs\ExpireUnpaidOrderJob;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

// The orders are placed on Saturday 7 November 2026 at noon; each test moves the clock from there.
beforeEach(function (): void {
    travelTo('2026-11-07 12:00:00');
});

/**
 * Sign in as the customer of a new store with a 100 ml bottle open for decants, and order a 5 ml decant of it through
 * the API; answer the store, the bottle and the order and line the API created.
 *
 * @return array{business: StaffedBusiness, bottle: string, order: string, line: string}
 */
function orderAnUnpaidDecant(?int $expiryMinutes = null): array
{
    $business = actingAsCustomer();
    if ($expiryMinutes !== null) {
        DB::table('businesses')->where('id', $business->business->id)->update(['settings' => json_encode(['unpaid_expiry_minutes' => $expiryMinutes])]);
    }
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 15000, 15000]]);
    $bottle = DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[0]->id->value, 100)->create();

    $response = postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [['botella_id' => $bottle->id, 'ml' => 5]]])
        ->assertCreated()->assertJsonPath('data.estado', 'pendiente_de_pago')->assertJsonPath('data.por_cobrar_ahora', 800);

    return ['business' => $business, 'bottle' => $bottle->id, 'order' => (string) $response->json('data.id'), 'line' => (string) $response->json('data.lineas.0.id')];
}

/** The payment the customer started for the decant, still waiting for ONVO. */
function startedDecantPayment(StaffedBusiness $business, string $orderId): PaymentModel
{
    app(BusinessTransaction::class)->scope($business->business->id);

    return StoredPayments::create($business, OrderModel::query()->findOrFail($orderId), amount: 800, purpose: 'full');
}

/** Run the next job of the database queue that is available at this moment, as the worker of production does. */
function workTheNextAvailableJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

/**
 * What the expiry of the order touches, read as its store.
 *
 * @return array{order: string, line: string, remaining_ml: int, reserved_ml: int, releases: int}
 */
function expiryState(StaffedBusiness $business, string $orderId, string $lineId, string $bottleId): array
{
    app(BusinessTransaction::class)->scope($business->business->id);

    return [
        'order' => (string) DB::table('orders')->where('id', $orderId)->value('status'),
        'line' => (string) DB::table('order_lines')->where('id', $lineId)->value('status'),
        'remaining_ml' => (int) DB::table('decant_bottles')->where('id', $bottleId)->value('remaining_ml'),
        'reserved_ml' => (int) DB::table('decant_bottles')->where('id', $bottleId)->value('reserved_ml'),
        'releases' => DB::table('decant_movements')->where('decant_bottle_id', $bottleId)->where('type', 'release')->count(),
    ];
}

it('R25: leaves the expiry of an unpaid order in the queue, available at its deadline', function (?int $expiryMinutes, string $deadline): void {
    Queue::fake();

    $placed = orderAnUnpaidDecant($expiryMinutes);

    Queue::assertPushed(ExpireUnpaidOrderJob::class, fn (ExpireUnpaidOrderJob $job): bool => $job->orderId === $placed['order']
        && $job->businessId === $placed['business']->business->id
        && $job->delay instanceof DateTimeInterface && $job->delay->format(DATE_ATOM) === $deadline);
})->with([
    'the 30 minutes of the specification' => [null, '2026-11-07T12:30:00-06:00'],
    'the minutes the store set' => [45, '2026-11-07T12:45:00-06:00'],
]);

it('leaves no expiry for an order that has nothing to pay now', function (): void {
    Queue::fake();
    $business = actingAsCustomer();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 0, 0]]);

    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [['presentacion_id' => $perfume->variants[0]->id->value, 'cantidad' => 1]]])
        ->assertCreated()->assertJsonPath('data.estado', 'confirmado');

    Queue::assertNotPushed(ExpireUnpaidOrderJob::class);
});

it('R25: expires an unpaid decant order with the queue at 30 minutes, gives its ml back to the bottle and closes its payment', function (): void {
    config(['queue.default' => 'database']);
    $placed = orderAnUnpaidDecant();
    $payment = startedDecantPayment($placed['business'], $placed['order']);

    travelTo('2026-11-07 12:29:59');
    workTheNextAvailableJob();

    expect(expiryState($placed['business'], $placed['order'], $placed['line'], $placed['bottle']))
        ->toBe(['order' => 'pending_payment', 'line' => 'reserved', 'remaining_ml' => 100, 'reserved_ml' => 5, 'releases' => 0]);

    travelTo('2026-11-07 12:30:00');
    workTheNextAvailableJob();

    expect(expiryState($placed['business'], $placed['order'], $placed['line'], $placed['bottle']))
        ->toBe(['order' => 'expired', 'line' => 'released', 'remaining_ml' => 100, 'reserved_ml' => 0, 'releases' => 1])
        ->and(DB::table('payments')->where('id', $payment->id)->value('status'))->toBe('expired')
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    // The system released the ml: the movement names the line and the reason, and no user.
    expect(DB::table('decant_movements')->where('type', 'release')->first(['order_line_id', 'user_id', 'ml', 'reason']))
        ->toEqual((object) ['order_line_id' => $placed['line'], 'user_id' => null, 'ml' => 5, 'reason' => 'El pedido venció sin pago.']);
    // The customer sees the order expired, with nothing left to pay (#82).
    getJson('/api/v1/cuenta/pedidos/'.$placed['order'])->assertOk()->assertJsonPath('data.estado', 'vencido')
        ->assertJsonPath('data.por_cobrar_ahora', 0)->assertJsonPath('data.vence_en', null)->assertJsonPath('data.lineas.0.estado', 'liberada');
});

it('R25: leaves an order paid at 29 minutes as it is when its deadline arrives', function (): void {
    config(['queue.default' => 'database', 'payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
    $placed = orderAnUnpaidDecant();
    $payment = startedDecantPayment($placed['business'], $placed['order']);
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/'.$payment->provider_reference => Http::response(['id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $payment->capture_reference]),
        'api.onvopay.com/v1/payment-intents/'.$payment->capture_reference => Http::response(['id' => $payment->capture_reference, 'mode' => 'test', 'amount' => 80_000, 'currency' => 'CRC', 'status' => 'succeeded']),
    ]);

    // ONVO confirms the payment at 12:29, and the worker applies its notice before the deadline.
    travelTo('2026-11-07 12:29:00');
    postJson('/api/v1/webhooks/onvo', ['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $payment->provider_reference, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 80_000, 'currency' => 'CRC',
        'metadata' => ['businessId' => $placed['business']->business->id, 'paymentId' => $payment->id],
    ]], ['X-Webhook-Secret' => 'webhook_secret_fictitious'])->assertOk();
    workTheNextAvailableJob();

    travelTo('2026-11-07 12:30:00');
    workTheNextAvailableJob();

    expect(expiryState($placed['business'], $placed['order'], $placed['line'], $placed['bottle']))
        ->toBe(['order' => 'confirmed', 'line' => 'reserved', 'remaining_ml' => 100, 'reserved_ml' => 5, 'releases' => 0])
        ->and(DB::table('payments')->where('id', $payment->id)->value('status'))->toBe('succeeded')
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('R25: gives the ml back once when a second copy of the job of an expired order runs', function (): void {
    config(['queue.default' => 'database']);
    $placed = orderAnUnpaidDecant();
    travelTo('2026-11-07 12:30:00');
    workTheNextAvailableJob();

    // A copy without its unique key, as php artisan queue:retry leaves it.
    Queue::connection('database')->push(new ExpireUnpaidOrderJob($placed['order'], $placed['business']->business->id));
    workTheNextAvailableJob();

    expect(expiryState($placed['business'], $placed['order'], $placed['line'], $placed['bottle']))
        ->toBe(['order' => 'expired', 'line' => 'released', 'remaining_ml' => 100, 'reserved_ml' => 0, 'releases' => 1])
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('never expires an order before its deadline, even when a copy of its job runs early', function (): void {
    config(['queue.default' => 'database']);
    $placed = orderAnUnpaidDecant();

    travelTo('2026-11-07 12:10:00');
    Queue::connection('database')->push(new ExpireUnpaidOrderJob($placed['order'], $placed['business']->business->id));
    workTheNextAvailableJob();

    // The early copy finished without changing anything; the job of the deadline still waits for 12:30.
    expect(expiryState($placed['business'], $placed['order'], $placed['line'], $placed['bottle']))
        ->toBe(['order' => 'pending_payment', 'line' => 'reserved', 'remaining_ml' => 100, 'reserved_ml' => 5, 'releases' => 0])
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('does not change an order that was paid, cancelled or already expired when its job runs', function (string $status): void {
    config(['queue.default' => 'database']);
    $business = StaffedBusiness::create();
    $stored = StoredOrders::create($business, $status, withBottle: false, withDecant: true);
    $bottle = $stored['bottle'];
    assert($bottle instanceof DecantBottleModel);
    $line = DB::table('order_lines')->where('order_id', $stored['order']->id)->value('id');
    $before = expiryState($business, $stored['order']->id, (string) $line, $bottle->id);

    travelTo('2026-11-07 13:00:00');
    ExpireUnpaidOrderJob::dispatch($stored['order']->id, $business->business->id);
    workTheNextAvailableJob();

    expect(expiryState($business, $stored['order']->id, (string) $line, $bottle->id))->toBe($before)
        ->and($before['order'])->toBe($status)
        ->and(DB::table('jobs')->count())->toBe(0);
})->with(['confirmed', 'cancelled', 'expired']);

it('R25: stores the expiry only while the row of the order still waits for its payment', function (): void {
    $placed = orderAnUnpaidDecant();
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($placed['business']->business->id)));
    $orders = app(OrderRepository::class);
    $order = $orders->find(new OrderId($placed['order']));
    assert($order !== null);

    // The confirmation of its payment committed after this operation read the order.
    DB::table('orders')->where('id', $placed['order'])->update(['status' => 'confirmed', 'paid' => 800]);
    $order->expire(new DateTimeImmutable('2026-11-07T12:30:00-06:00'));
    $order->line($placed['line'])->advanceTo(OrderLineStatus::Released);

    expect($orders->saveIfStatus($order, OrderStatus::PendingPayment))->toBeFalse()
        ->and(DB::table('orders')->where('id', $placed['order'])->first(['status', 'paid']))->toEqual((object) ['status' => 'confirmed', 'paid' => 800])
        ->and(DB::table('order_lines')->where('id', $placed['line'])->value('status'))->toBe('reserved');
});
