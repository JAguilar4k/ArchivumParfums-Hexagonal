<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Decants\Packs\Infrastructure\Jobs\ExpireDecantPackJob;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

// Each pack is bought on Tuesday 10 November 2026 at 15:00; each test moves the clock from there.
beforeEach(function (): void {
    travelTo(new DateTimeImmutable('2026-11-10T15:00:00-06:00'));
    config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
    Http::preventStrayRequests();
    // ONVO creates a checkout named after each new payment and answers the server's queries about it: paid.
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/one-time-link' => fn (Request $request): PromiseInterface => Http::response([
            'id' => 'session-'.$request['metadata']['paymentId'], 'mode' => 'test',
            'url' => 'https://checkout.onvopay.com/pay/session-'.$request['metadata']['paymentId'], 'paymentIntentId' => 'intent-'.$request['metadata']['paymentId'],
        ], 201),
        'api.onvopay.com/v1/checkout/sessions/*' => fn (Request $request): PromiseInterface => Http::response([
            'id' => basename($request->url()), 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => 'intent-'.Str::after(basename($request->url()), 'session-'),
        ]),
        'api.onvopay.com/v1/payment-intents/*' => fn (Request $request): PromiseInterface => Http::response([
            'id' => basename($request->url()), 'mode' => 'test', 'amount' => 4_000_000, 'currency' => 'CRC', 'status' => 'succeeded',
        ]),
    ]);
});

/**
 * Sign in as the customer of a new store that sells the offer Descubrimiento (5 decants of 5 ml, ₡40 000, 180 days)
 * and buy it through the API; answer the store, the pack and its payment. The store gives $expiryMinutes to pay an
 * unpaid purchase, or the 30 of every store.
 *
 * @return array{business: StaffedBusiness, pack: string, payment: string}
 */
function buyAnExpiringPack(?int $expiryMinutes = null): array
{
    $business = actingAsCustomer();
    if ($expiryMinutes !== null) {
        $settings = json_decode((string) DB::table('businesses')->where('id', $business->business->id)->value('settings'), true);
        DB::table('businesses')->where('id', $business->business->id)
            ->update(['settings' => json_encode([...(is_array($settings) ? $settings : []), 'unpaid_expiry_minutes' => $expiryMinutes])]);
    }
    $offer = DecantPackPlanModel::factory()->create(['business_id' => $business->business->id]);

    $bought = postJson('/api/v1/tiendas/'.$business->business->slug.'/paquetes/'.$offer->id.'/compra')->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente_de_pago');

    return ['business' => $business, 'pack' => (string) $bought->json('data.id'), 'payment' => (string) $bought->json('data.pago.id')];
}

/**
 * The verified notice ONVO sends when the checkout of the payment of the pack was paid.
 *
 * @param  array{business: StaffedBusiness, pack: string, payment: string}  $bought
 * @return TestResponse<JsonResponse>
 */
function noticeOfThePaidExpiringPack(array $bought): TestResponse
{
    return postJson('/api/v1/webhooks/onvo', [
        'type' => 'checkout-session.succeeded',
        'data' => ['mode' => 'test', 'id' => 'session-'.$bought['payment'], 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 4_000_000,
            'currency' => 'CRC', 'metadata' => ['businessId' => $bought['business']->business->id, 'paymentId' => $bought['payment']]],
    ], ['X-Webhook-Secret' => 'webhook_secret_fictitious']);
}

/** Run the next job of the database queue that is available at this moment, as the worker of production does. */
function runTheNextPackJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    assert($worker instanceof PendingCommand);
    // A pending command runs when it is destroyed; run() runs it here, before the assertions.
    $worker->assertSuccessful()->run();
}

/**
 * What the expiry of the pack touches, read as its store: the pack, its payments, the jobs that wait or failed and the
 * refunds of the store.
 *
 * @param  array{business: StaffedBusiness, pack: string, payment: string}  $bought
 * @return array{pack: string, remaining: int, payments: list<string>, jobs: int, failed: int, refunds: int}
 */
function expiringPackState(array $bought): array
{
    app(BusinessTransaction::class)->scope($bought['business']->business->id);
    $pack = DB::table('decant_packs')->where('id', $bought['pack'])->first(['status', 'remaining_decants']);
    assert($pack !== null);

    return [
        'pack' => (string) $pack->status,
        'remaining' => (int) $pack->remaining_decants,
        'payments' => array_values(array_map(strval(...), DB::table('payments')->where('decant_pack_id', $bought['pack'])->orderBy('id')->pluck('status')->all())),
        'jobs' => DB::table('jobs')->count(),
        'failed' => DB::table('failed_jobs')->count(),
        'refunds' => DB::table('refunds')->count(),
    ];
}

/**
 * With the database queue: buy a pack at 15:00, confirm its payment with a verified notice of ONVO at 15:05 that the
 * worker applies, and run at 15:30 the job of its payment deadline, which finds it paid. Only the job of the end of its
 * validity, on 2027-05-09 at 15:05, is left in the queue.
 *
 * @return array{business: StaffedBusiness, pack: string, payment: string}
 */
function aPaidExpiringPack(): array
{
    config(['queue.default' => 'database']);
    $bought = buyAnExpiringPack();
    travelTo(new DateTimeImmutable('2026-11-10T15:05:00-06:00'));
    noticeOfThePaidExpiringPack($bought)->assertOk();
    runTheNextPackJob();

    // The job of the validity waits next to the one of the payment deadline: each pack has one per reason.
    expect(expiringPackState($bought))->toBe(['pack' => 'active', 'remaining' => 5, 'payments' => ['succeeded'], 'jobs' => 2, 'failed' => 0, 'refunds' => 0]);

    travelTo(new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'active', 'remaining' => 5, 'payments' => ['succeeded'], 'jobs' => 1, 'failed' => 0, 'refunds' => 0]);

    return $bought;
}

it('R33: leaves the expiry of an unpaid pack in the queue, available at the payment deadline of its store', function (?int $expiryMinutes, string $deadline) {
    Queue::fake();

    $bought = buyAnExpiringPack($expiryMinutes);

    Queue::assertPushed(ExpireDecantPackJob::class, 1);
    Queue::assertPushed(ExpireDecantPackJob::class, fn (ExpireDecantPackJob $job): bool => $job->packId === $bought['pack']
        && $job->businessId === $bought['business']->business->id && $job->reason === ExpiryReason::Unpaid
        && $job->delay instanceof DateTimeInterface && $job->delay->format(DATE_ATOM) === $deadline);
})->with([
    'the 30 minutes of the specification' => [null, '2026-11-10T15:30:00-06:00'],
    'the minutes the store set' => [45, '2026-11-10T15:45:00-06:00'],
]);

it('R33: leaves the expiry of the validity in the queue when the payment of the pack is confirmed, counted from then', function () {
    // Only the jobs of the expiry wait aside; the notice of ONVO is applied by the sync queue inside its request.
    $queue = Queue::fake([ExpireDecantPackJob::class]);
    $bought = buyAnExpiringPack();
    travelTo(new DateTimeImmutable('2026-11-10T15:05:00-06:00'));

    noticeOfThePaidExpiringPack($bought)->assertOk();

    $jobs = $queue->pushed(ExpireDecantPackJob::class)->map(fn (ExpireDecantPackJob $job): array => [
        $job->packId, $job->businessId, $job->reason, $job->delay instanceof DateTimeInterface ? $job->delay->format(DATE_ATOM) : null,
    ])->all();
    expect($jobs)->toBe([
        [$bought['pack'], $bought['business']->business->id, ExpiryReason::Unpaid, '2026-11-10T15:30:00-06:00'],
        [$bought['pack'], $bought['business']->business->id, ExpiryReason::ValidityEnded, '2027-05-09T15:05:00-06:00'],
    ]);
});

it('R33: expires an unpaid pack with the queue at 30 minutes, together with its pending payment, and refunds nothing', function (?string $payment, string $closed) {
    config(['queue.default' => 'database']);
    $bought = buyAnExpiringPack();
    if ($payment !== null) {
        app(BusinessTransaction::class)->run($bought['business']->business->id, fn (): int => DB::table('payments')->where('id', $bought['payment'])->update(['status' => $payment]));
    }

    travelTo(new DateTimeImmutable('2026-11-10T15:29:59-06:00'));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'pending_payment', 'remaining' => 5, 'payments' => [$payment ?? 'pending'], 'jobs' => 1, 'failed' => 0, 'refunds' => 0]);

    travelTo(new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'expired', 'remaining' => 5, 'payments' => [$closed], 'jobs' => 0, 'failed' => 0, 'refunds' => 0]);
    // The customer sees the pack expired (#148).
    getJson('/api/v1/cuenta/paquetes-comprados')->assertOk()->assertJsonPath('data.0.id', $bought['pack'])->assertJsonPath('data.0.estado', 'vencido');
})->with([
    'its payment still waits for ONVO' => [null, 'expired'],
    'its payment failed' => ['failed', 'failed'],
]);

it('R35: expires a paid pack with the queue when its validity ends, even with decants left, and refunds nothing', function () {
    $bought = aPaidExpiringPack();
    $business = $bought['business']->business;
    $perfume = StoredPerfumes::create($business->id, variants: [[100, 120000, 120000]]);
    $bottle = DecantBottleModel::factory()->forPresentation($business->id, $perfume->variants[0]->id->value, 100)->create();
    $redeem = fn (): TestResponse => postJson('/api/v1/tiendas/'.$business->slug.'/pedidos', ['lineas' => [['botella_id' => $bottle->id, 'ml' => 5, 'paquete_id' => $bought['pack']]]]);

    // The customer redeems one decant in December: the pack keeps 4.
    travelTo(new DateTimeImmutable('2026-12-01T10:00:00-06:00'));
    $redeem()->assertCreated()->assertJsonPath('data.estado', 'confirmado');

    travelTo(new DateTimeImmutable('2027-05-09T15:04:59-06:00'));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'active', 'remaining' => 4, 'payments' => ['succeeded'], 'jobs' => 1, 'failed' => 0, 'refunds' => 0]);

    // From the end of its validity the redemption refuses the pack as expired, before the worker runs its job (#146).
    travelTo(new DateTimeImmutable('2027-05-09T15:05:00-06:00'));
    $redeem()->assertConflict()->assertJsonPath('detail', __('packs.expired'));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'expired', 'remaining' => 4, 'payments' => ['succeeded'], 'jobs' => 0, 'failed' => 0, 'refunds' => 0])
        // The decant redeemed before its validity ended stays redeemed (precisions of R32-R35).
        ->and(DB::table('decant_pack_redemptions')->where('decant_pack_id', $bought['pack'])->count())->toBe(1);
});

it('R35: leaves an exhausted pack as it is when the job of its validity runs', function () {
    $bought = aPaidExpiringPack();
    // The customer redeemed its five decants.
    app(BusinessTransaction::class)->run($bought['business']->business->id, fn (): int => DB::table('decant_packs')->where('id', $bought['pack'])->update(['status' => 'exhausted', 'remaining_decants' => 0]));

    travelTo(new DateTimeImmutable('2027-05-09T15:05:00-06:00'));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'exhausted', 'remaining' => 0, 'payments' => ['succeeded'], 'jobs' => 0, 'failed' => 0, 'refunds' => 0]);
});

it('gives the same result when a second copy of the job of an expired pack runs', function () {
    config(['queue.default' => 'database']);
    $bought = buyAnExpiringPack();
    travelTo(new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    runTheNextPackJob();

    // A copy without its unique key, as php artisan queue:retry leaves it.
    Queue::connection('database')->push(new ExpireDecantPackJob($bought['pack'], $bought['business']->business->id, ExpiryReason::Unpaid));
    runTheNextPackJob();

    expect(expiringPackState($bought))->toBe(['pack' => 'expired', 'remaining' => 5, 'payments' => ['expired'], 'jobs' => 0, 'failed' => 0, 'refunds' => 0]);
});

it('never expires a pack before its deadline, even when a copy of its job runs early', function () {
    config(['queue.default' => 'database']);
    $bought = buyAnExpiringPack();

    travelTo(new DateTimeImmutable('2026-11-10T15:10:00-06:00'));
    Queue::connection('database')->push(new ExpireDecantPackJob($bought['pack'], $bought['business']->business->id, ExpiryReason::Unpaid));
    runTheNextPackJob();

    // The early copy finished without changing anything; the job of the deadline still waits for 15:30.
    expect(expiringPackState($bought))->toBe(['pack' => 'pending_payment', 'remaining' => 5, 'payments' => ['pending'], 'jobs' => 1, 'failed' => 0, 'refunds' => 0]);
});

it('keeps one job per pack and reason in the queue', function () {
    config(['queue.default' => 'database']);
    $bought = buyAnExpiringPack();
    $businessId = $bought['business']->business->id;

    // The job of the payment deadline already waits: the same one again leaves nothing new, the one of the validity does.
    ExpireDecantPackJob::dispatch($bought['pack'], $businessId, ExpiryReason::Unpaid)->delay(new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    ExpireDecantPackJob::dispatch($bought['pack'], $businessId, ExpiryReason::ValidityEnded)->delay(new DateTimeImmutable('2027-05-09T15:05:00-06:00'));

    expect(DB::table('jobs')->orderBy('id')->pluck('available_at')->all())->toBe([
        (new DateTimeImmutable('2026-11-10T15:30:00-06:00'))->getTimestamp(),
        (new DateTimeImmutable('2027-05-09T15:05:00-06:00'))->getTimestamp(),
    ]);
});
