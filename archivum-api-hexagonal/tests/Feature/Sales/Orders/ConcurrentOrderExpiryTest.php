<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Infrastructure\Jobs\ExpireUnpaidOrderJob;
use Src\Sales\Orders\Infrastructure\Persistence\Repositories\EloquentOrderRepository;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\HoldLockedOrderForCompetingOperation;

pest()->use(RefreshDatabaseAsOwner::class);

it('R25, R38: ends an expiry and the confirmation of its payment that arrive at the same time in only one of two outcomes', function (string $first, array $outcome): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $bottleId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    $lineId = (string) Str::uuid7();
    $paymentId = (string) Str::uuid7();
    $session = 'session-expiry-'.$paymentId;
    $intent = 'intent-expiry-'.$paymentId;
    $slug = 'expiry-race-'.Str::uuid7();
    $signal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'archivum-expiry-race-'.Str::uuid7().'.locked';

    // An order of one 5 ml decant whose 30 minutes already passed, with the payment the customer started.
    $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $perfumeId, $variantId, $bottleId, $orderId, $lineId, $paymentId, $session, $intent, $slug): void {
        $timestamps = ['created_at' => now()->subMinutes(31), 'updated_at' => now()->subMinutes(31)];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 15000, ...$timestamps]);
        $owner->table('decant_bottles')->insert(['id' => $bottleId, 'business_id' => $businessId, 'perfume_variant_id' => $variantId, 'total_ml' => 100, 'remaining_ml' => 100, 'reserved_ml' => 5, 'status' => 'open', 'opened_at' => now()->subDay(), ...$timestamps]);
        $owner->table('orders')->insert(['id' => $orderId, 'business_id' => $businessId, 'customer_id' => $customerId, 'status' => 'pending_payment', 'subtotal' => 800, 'due_now' => 800, 'paid' => 0, 'balance' => 0, 'deposit_percent' => 50, 'expires_at' => now()->subMinute(), ...$timestamps]);
        $owner->table('order_lines')->insert(['id' => $lineId, 'business_id' => $businessId, 'order_id' => $orderId, 'kind' => 'decant', 'perfume_variant_id' => $variantId, 'decant_bottle_id' => $bottleId, 'ml' => 5, 'quantity' => 1, 'unit_price' => 800, 'status' => 'reserved', ...$timestamps]);
        $owner->table('decant_movements')->insert(['business_id' => $businessId, 'decant_bottle_id' => $bottleId, 'type' => 'reservation', 'ml' => 5, 'order_line_id' => $lineId, 'user_id' => $userId, 'created_at' => $timestamps['created_at']]);
        $owner->table('payments')->insert(['id' => $paymentId, 'business_id' => $businessId, 'order_id' => $orderId, 'purpose' => 'full', 'amount' => 800, 'gateway' => 'onvo', 'status' => 'pending', 'charged_currency' => 'CRC', 'charged_amount' => 800, 'provider_reference' => $session, 'checkout_reference' => $session, 'capture_reference' => $intent, ...$timestamps]);
    });
    $notice = json_encode(['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 80_000, 'currency' => 'CRC',
        'metadata' => ['businessId' => $businessId, 'paymentId' => $paymentId],
    ]], JSON_THROW_ON_ERROR);

    // The job of the deadline, as the worker runs it: through its middleware, in a transaction of its business.
    $expire = function () use ($orderId, $businessId, $signal, $first): array {
        app()->singleton(HoldLockedOrderForCompetingOperation::class, fn (): HoldLockedOrderForCompetingOperation => new HoldLockedOrderForCompetingOperation(app(EloquentOrderRepository::class), $first === 'expiry' ? $signal : null));
        app()->bind(OrderRepository::class, fn (): HoldLockedOrderForCompetingOperation => app(HoldLockedOrderForCompetingOperation::class));
        if ($first !== 'expiry') {
            HoldLockedOrderForCompetingOperation::waitUntilLocked($signal);
        }
        ExpireUnpaidOrderJob::dispatchSync($orderId, $businessId);

        return ['overlap' => app(HoldLockedOrderForCompetingOperation::class)->observedOverlap];
    };

    // ONVO's notice of the paid checkout, applied by the sync queue inside its request; only the request of a refund to
    // ONVO is faked.
    $notify = function () use ($notice, $session, $intent, $signal, $first): array {
        config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
        Http::preventStrayRequests();
        Http::fake([
            'api.onvopay.com/v1/checkout/sessions/'.$session => Http::response(['id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $intent]),
            'api.onvopay.com/v1/payment-intents/'.$intent => Http::response(['id' => $intent, 'mode' => 'test', 'amount' => 80_000, 'currency' => 'CRC', 'status' => 'succeeded']),
        ]);
        $queue = Queue::fake([RequestRefundJob::class]);
        app()->singleton(HoldLockedOrderForCompetingOperation::class, fn (): HoldLockedOrderForCompetingOperation => new HoldLockedOrderForCompetingOperation(app(EloquentOrderRepository::class), $first === 'notice' ? $signal : null));
        app()->bind(OrderRepository::class, fn (): HoldLockedOrderForCompetingOperation => app(HoldLockedOrderForCompetingOperation::class));
        if ($first !== 'notice') {
            HoldLockedOrderForCompetingOperation::waitUntilLocked($signal);
        }
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/webhooks/onvo', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SECRET' => 'webhook_secret_fictitious'], $notice);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'overlap' => app(HoldLockedOrderForCompetingOperation::class)->observedOverlap,
            'queries' => Http::recorded()->count(),
            'queued' => $queue->pushed(RequestRefundJob::class)->count(),
        ];
    };

    // audit_logs is left out of the tables to empty: nobody can change it, and its entries name a store only this test has.
    try {
        [$expired, $notified] = inParallel(['refunds', 'webhook_events', 'payments', 'decant_movements', 'order_lines', 'orders', 'decant_bottles', 'perfume_variants', 'perfumes', 'customers', 'users', 'businesses'], $expire, $notify);
    } finally {
        if (file_exists($signal)) {
            unlink($signal);
        }
    }

    // The second one waited for the lock of the order and then saw what the first one committed. A notice that finds
    // the payment closed by the expiry still asks ONVO, which charged it: the charge is kept and its refund requested.
    expect($expired)->toBe(['overlap' => $first === 'expiry'])
        ->and($notified)->toBe(['status' => 200, 'overlap' => $first === 'notice', 'queries' => 2, 'queued' => $first === 'expiry' ? 1 : 0]);

    $state = $owner->transaction(function () use ($owner, $businessId, $orderId, $lineId, $paymentId, $bottleId): array {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);

        return [
            'order' => (string) $owner->table('orders')->where('id', $orderId)->value('status'),
            'paid' => (int) $owner->table('orders')->where('id', $orderId)->value('paid'),
            'line' => (string) $owner->table('order_lines')->where('id', $lineId)->value('status'),
            'payment' => (string) $owner->table('payments')->where('id', $paymentId)->value('status'),
            'reserved_ml' => (int) $owner->table('decant_bottles')->where('id', $bottleId)->value('reserved_ml'),
            'releases' => $owner->table('decant_movements')->where('type', 'release')->count(),
            'refunds' => $owner->table('refunds')->get(['payment_id', 'amount', 'status'])->map(fn (object $refund): array => (array) $refund)->all(),
            'notice' => (string) $owner->table('webhook_events')->value('status'),
        ];
    });

    // Never an expired order whose charge nobody refunds, or a confirmed order whose ml went back to the bottle (#192).
    expect($state)->toBe([...$outcome, 'refunds' => $first === 'expiry' ? [['payment_id' => $paymentId, 'amount' => 800, 'status' => 'pending']] : [], 'notice' => 'processed']);
})->with([
    // The notice finds the payment closed by the expiry: ONVO charged it, so the order keeps it until its refund.
    'the expiry locks the order first' => ['expiry', ['order' => 'expired', 'paid' => 800, 'line' => 'released', 'payment' => 'succeeded', 'reserved_ml' => 0, 'releases' => 1]],
    // The expiry finds the order paid and releases nothing.
    'the notice locks the order first' => ['notice', ['order' => 'confirmed', 'paid' => 800, 'line' => 'reserved', 'payment' => 'succeeded', 'reserved_ml' => 5, 'releases' => 0]],
]);
