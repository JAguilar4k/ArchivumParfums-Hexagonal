<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Infrastructure\Persistence\Repositories\EloquentOrderRepository;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\HoldLockedOrderForCompetingOperation;

pest()->use(RefreshDatabaseAsOwner::class);

it('R38, R40: ends a charge confirmed while the customer cancels in a cancelled order with one full refund', function (string $first): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    $paymentId = (string) Str::uuid7();
    $session = 'session-late-'.$paymentId;
    $intent = 'intent-late-'.$paymentId;
    $plainToken = 'fictitious-late-charge-token-'.Str::uuid7();
    $slug = 'late-charge-'.Str::uuid7();
    $signal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'archivum-late-charge-'.Str::uuid7().'.locked';

    $tokenId = $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $perfumeId, $variantId, $orderId, $paymentId, $session, $intent, $plainToken, $slug): int {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 15000, ...$timestamps]);
        $owner->table('orders')->insert(['id' => $orderId, 'business_id' => $businessId, 'customer_id' => $customerId, 'status' => 'pending_payment', 'subtotal' => 15000, 'due_now' => 7500, 'paid' => 0, 'balance' => 7500, 'deposit_percent' => 50, 'expires_at' => now()->addMinutes(30), ...$timestamps]);
        $owner->table('order_lines')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'order_id' => $orderId, 'kind' => 'bottle', 'perfume_variant_id' => $variantId, 'quantity' => 1, 'unit_price' => 15000, 'status' => 'pending', ...$timestamps]);
        $owner->table('payments')->insert(['id' => $paymentId, 'business_id' => $businessId, 'order_id' => $orderId, 'purpose' => 'deposit', 'amount' => 7500, 'gateway' => 'onvo', 'status' => 'pending', 'charged_currency' => 'CRC', 'charged_amount' => 7500, 'provider_reference' => $session, 'checkout_reference' => $session, 'capture_reference' => $intent, ...$timestamps]);

        return (int) $owner->table('personal_access_tokens')->insertGetId(['tokenable_type' => UserModel::class, 'tokenable_id' => $userId, 'name' => 'late-charge-test', 'token' => hash('sha256', $plainToken),
            'abilities' => json_encode(Ability::namesGrantedTo(AccountRole::Customer), JSON_THROW_ON_ERROR), ...$timestamps]);
    });
    $token = $tokenId.'|'.$plainToken;
    $notice = json_encode(['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 750_000, 'currency' => 'CRC',
        'metadata' => ['businessId' => $businessId, 'paymentId' => $paymentId],
    ]], JSON_THROW_ON_ERROR);

    // ONVO's notice of the paid checkout, applied by the sync queue inside its request.
    $notify = function () use ($notice, $session, $intent, $signal, $first): array {
        config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
        Http::preventStrayRequests();
        Http::fake([
            'api.onvopay.com/v1/checkout/sessions/'.$session => Http::response(['id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $intent]),
            'api.onvopay.com/v1/payment-intents/'.$intent => Http::response(['id' => $intent, 'mode' => 'test', 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded']),
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
            'state' => json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['estado'] ?? null,
            'overlap' => app(HoldLockedOrderForCompetingOperation::class)->observedOverlap,
            'queued' => $queue->pushed(RequestRefundJob::class)->count(),
            'queries' => Http::recorded()->count(),
        ];
    };

    // The customer's cancellation of #83, with the token of the customer's account.
    $cancel = function () use ($orderId, $token, $signal, $first): array {
        Http::preventStrayRequests();
        $queue = Queue::fake([RequestRefundJob::class]);
        app()->singleton(HoldLockedOrderForCompetingOperation::class, fn (): HoldLockedOrderForCompetingOperation => new HoldLockedOrderForCompetingOperation(app(EloquentOrderRepository::class), $first === 'cancellation' ? $signal : null));
        app()->bind(OrderRepository::class, fn (): HoldLockedOrderForCompetingOperation => app(HoldLockedOrderForCompetingOperation::class));
        if ($first !== 'cancellation') {
            HoldLockedOrderForCompetingOperation::waitUntilLocked($signal);
        }
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/cuenta/pedidos/'.$orderId.'/cancelacion', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $order = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'] ?? [];

        return [
            'status' => $response->getStatusCode(),
            'state' => $order['estado'] ?? null,
            'paid' => $order['pagado'] ?? null,
            'overlap' => app(HoldLockedOrderForCompetingOperation::class)->observedOverlap,
            'queued' => $queue->pushed(RequestRefundJob::class)->count(),
        ];
    };

    // audit_logs is left out of the tables to empty: nobody can change it, and its entries name a store only this test has.
    try {
        [$notified, $cancelled] = inParallel(['refunds', 'webhook_events', 'payments', 'order_lines', 'orders', 'perfume_variants', 'perfumes', 'customers', 'personal_access_tokens', 'users', 'businesses'], $notify, $cancel);
    } finally {
        if (file_exists($signal)) {
            unlink($signal);
        }
    }

    // The second one waited for the lock of the order and then saw what the first one committed. A confirmed order is
    // cancelled with the refund of what it paid; a cancelled one keeps the late charge and its refund.
    expect($notified)->toMatchArray(['status' => 200, 'state' => 'recibido', 'overlap' => $first === 'notice', 'queued' => $first === 'notice' ? 0 : 1, 'queries' => 2])
        ->and($cancelled)->toMatchArray(['status' => 200, 'state' => 'cancelado', 'paid' => $first === 'notice' ? 7500 : 0, 'overlap' => $first === 'cancellation', 'queued' => $first === 'notice' ? 1 : 0]);
    $owner->transaction(function () use ($owner, $businessId, $orderId, $paymentId, $session, $userId, $first): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('orders')->where('id', $orderId)->first(['status', 'paid']))->toEqual((object) ['status' => 'cancelled', 'paid' => 7500])
            ->and($owner->table('payments')->where('id', $paymentId)->first(['status', 'refunded_amount']))->toEqual((object) ['status' => 'succeeded', 'refunded_amount' => 0])
            ->and($owner->table('refunds')->get(['payment_id', 'amount', 'status'])->all())->toEqual([(object) ['payment_id' => $paymentId, 'amount' => 7500, 'status' => 'pending']])
            ->and($owner->table('webhook_events')->where('event_id', 'checkout-session.succeeded:'.$session)->pluck('status')->all())->toBe(['processed'])
            // The customer's cancellation asked for the refund of a confirmed order; the system, for a late charge.
            ->and($owner->table('audit_logs')->where('action', 'refund.requested')->pluck('actor_user_id')->all())->toBe([$first === 'notice' ? $userId : null]);
    });
})->with([
    'the notice locks the order first' => ['notice'],
    'the cancellation locks the order first' => ['cancellation'],
]);
