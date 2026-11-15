<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\DatabaseWebhookEventLog;
use Tests\Support\Payments\HoldKeptNoticeForCompetingNotice;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('R38: applies the same notice sent twice at the same time only once', function (): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    $paymentId = (string) Str::uuid7();
    $session = 'session-parallel-'.$paymentId;
    $intent = 'intent-parallel-'.$paymentId;
    $slug = 'parallel-notice-'.Str::uuid7();

    $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $perfumeId, $variantId, $orderId, $paymentId, $session, $intent, $slug): void {
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
    });
    $notice = json_encode(['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 750_000, 'currency' => 'CRC',
        'metadata' => ['businessId' => $businessId, 'paymentId' => $paymentId],
    ]], JSON_THROW_ON_ERROR);

    $send = function () use ($notice, $session, $intent): array {
        config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
        Http::preventStrayRequests();
        Http::fake([
            'api.onvopay.com/v1/checkout/sessions/'.$session => Http::response(['id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $intent]),
            'api.onvopay.com/v1/payment-intents/'.$intent => Http::response(['id' => $intent, 'mode' => 'test', 'amount' => 750_000, 'currency' => 'CRC', 'status' => 'succeeded']),
        ]);
        app()->singleton(HoldKeptNoticeForCompetingNotice::class, fn (): HoldKeptNoticeForCompetingNotice => new HoldKeptNoticeForCompetingNotice(app(DatabaseWebhookEventLog::class)));
        app()->bind(WebhookEventLog::class, fn (): HoldKeptNoticeForCompetingNotice => app(HoldKeptNoticeForCompetingNotice::class));
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/webhooks/onvo', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SECRET' => 'webhook_secret_fictitious'], $notice);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'state' => json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['estado'] ?? null,
            'overlap' => app(HoldKeptNoticeForCompetingNotice::class)->observedOverlap,
            'queries' => Http::recorded()->count(),
        ];
    };

    $results = inParallel(['webhook_events', 'payments', 'order_lines', 'orders', 'perfume_variants', 'perfumes', 'customers', 'users', 'businesses'], $send, $send);

    // The second arrival waited for the first one on the unique key of the event and then kept nothing.
    expect(array_column($results, 'status'))->toBe([200, 200])
        ->and(array_column($results, 'state'))->toEqualCanonicalizing(['recibido', 'repetido'])
        ->and(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false])
        ->and(array_column($results, 'queries'))->toEqualCanonicalizing([2, 0]);
    $owner->transaction(function () use ($owner, $businessId, $orderId, $paymentId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('payments')->where('id', $paymentId)->value('status'))->toBe('succeeded')
            ->and($owner->table('orders')->where('id', $orderId)->first(['status', 'paid']))->toEqual((object) ['status' => 'confirmed', 'paid' => 7500])
            ->and($owner->table('webhook_events')->pluck('status')->all())->toBe(['processed']);
    });
});
