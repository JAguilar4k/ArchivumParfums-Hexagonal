<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Decants\Packs\Infrastructure\Jobs\ExpireDecantPackJob;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackRepository;
use Tests\Support\Decants\HoldLockedPackForCompetingOperation;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('R33: ends with only one of the expiry of an unpaid pack and the confirmation of its payment that arrive at the same time', function (string $first, array $outcome): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $planId = (string) Str::uuid7();
    $packId = (string) Str::uuid7();
    $paymentId = (string) Str::uuid7();
    $session = 'session-pack-expiry-'.$paymentId;
    $intent = 'intent-pack-expiry-'.$paymentId;
    $slug = 'pack-expiry-race-'.Str::uuid7();
    $signal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'archivum-pack-expiry-race-'.Str::uuid7().'.locked';

    // A pack of 5 decants whose 30 minutes to be paid already passed, with the payment the customer started.
    $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $planId, $packId, $paymentId, $session, $intent, $slug): void {
        $timestamps = ['created_at' => now()->subMinutes(31), 'updated_at' => now()->subMinutes(31)];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('decant_pack_plans')->insert(['id' => $planId, 'business_id' => $businessId, 'name' => 'Descubrimiento', 'decants_count' => 5, 'ml_per_decant' => 5,
            'price' => 40000, 'validity_days' => 180, 'max_price_per_ml' => 2500, ...$timestamps]);
        $owner->table('decant_packs')->insert(['id' => $packId, 'business_id' => $businessId, 'customer_id' => $customerId, 'decant_pack_plan_id' => $planId, 'total_decants' => 5,
            'remaining_decants' => 5, 'ml_per_decant' => 5, 'max_price_per_ml' => 2500, 'status' => 'pending_payment', 'expires_at' => now()->subMinute(), ...$timestamps]);
        $owner->table('payments')->insert(['id' => $paymentId, 'business_id' => $businessId, 'decant_pack_id' => $packId, 'purpose' => 'full', 'amount' => 40000, 'gateway' => 'onvo',
            'status' => 'pending', 'charged_currency' => 'CRC', 'charged_amount' => 40000, 'provider_reference' => $session, 'checkout_reference' => $session, 'capture_reference' => $intent, ...$timestamps]);
    });
    $notice = json_encode(['type' => 'checkout-session.succeeded', 'data' => [
        'id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 4_000_000, 'currency' => 'CRC',
        'metadata' => ['businessId' => $businessId, 'paymentId' => $paymentId],
    ]], JSON_THROW_ON_ERROR);

    // The job of the payment deadline, as the worker runs it: through its middleware, in a transaction of its business.
    $expire = function () use ($packId, $businessId, $signal, $first): array {
        app()->singleton(HoldLockedPackForCompetingOperation::class, fn (): HoldLockedPackForCompetingOperation => new HoldLockedPackForCompetingOperation(app(EloquentDecantPackRepository::class), $first === 'expiry' ? $signal : null));
        app()->bind(DecantPackRepository::class, fn (): HoldLockedPackForCompetingOperation => app(HoldLockedPackForCompetingOperation::class));
        if ($first !== 'expiry') {
            HoldLockedPackForCompetingOperation::waitUntilLocked($signal);
        }
        ExpireDecantPackJob::dispatchSync($packId, $businessId, ExpiryReason::Unpaid);

        return ['overlap' => app(HoldLockedPackForCompetingOperation::class)->observedOverlap];
    };

    // ONVO's notice of the paid checkout, applied by the sync queue inside its request.
    $notify = function () use ($notice, $session, $intent, $signal, $first): array {
        config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
        Http::preventStrayRequests();
        Http::fake([
            'api.onvopay.com/v1/checkout/sessions/'.$session => Http::response(['id' => $session, 'mode' => 'test', 'status' => 'complete', 'paymentIntentId' => $intent]),
            'api.onvopay.com/v1/payment-intents/'.$intent => Http::response(['id' => $intent, 'mode' => 'test', 'amount' => 4_000_000, 'currency' => 'CRC', 'status' => 'succeeded']),
        ]);
        app()->singleton(HoldLockedPackForCompetingOperation::class, fn (): HoldLockedPackForCompetingOperation => new HoldLockedPackForCompetingOperation(app(EloquentDecantPackRepository::class), $first === 'notice' ? $signal : null));
        app()->bind(DecantPackRepository::class, fn (): HoldLockedPackForCompetingOperation => app(HoldLockedPackForCompetingOperation::class));
        if ($first !== 'notice') {
            HoldLockedPackForCompetingOperation::waitUntilLocked($signal);
        }
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/webhooks/onvo', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SECRET' => 'webhook_secret_fictitious'], $notice);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'overlap' => app(HoldLockedPackForCompetingOperation::class)->observedOverlap,
            'queries' => Http::recorded()->count(),
        ];
    };

    try {
        [$expired, $notified] = inParallel(['webhook_events', 'payments', 'decant_packs', 'decant_pack_plans', 'customers', 'users', 'businesses'], $expire, $notify);
    } finally {
        if (file_exists($signal)) {
            unlink($signal);
        }
    }

    // The second one waited for the lock of the pack and then saw what the first one committed. A notice that finds the
    // payment closed does not ask ONVO.
    expect($expired)->toBe(['overlap' => $first === 'expiry'])
        ->and($notified)->toBe(['status' => 200, 'overlap' => $first === 'notice', 'queries' => $first === 'notice' ? 2 : 0]);

    $state = $owner->transaction(function () use ($owner, $businessId, $packId, $paymentId): array {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $pack = $owner->table('decant_packs')->where('id', $packId)->first(['status', 'remaining_decants', 'expires_at']);
        assert($pack !== null);

        return [
            'pack' => (string) $pack->status,
            'valid_for_days' => (int) round((strtotime((string) $pack->expires_at) - time()) / 86400),
            'payment' => (string) $owner->table('payments')->where('id', $paymentId)->value('status'),
            'remaining' => (int) $pack->remaining_decants,
            'refunds' => $owner->table('refunds')->count(),
            'notice' => (string) $owner->table('webhook_events')->value('status'),
        ];
    });

    // Never an expired pack with a confirmed payment, or an active pack whose payment the expiry closed.
    expect($state)->toBe([...$outcome, 'remaining' => 5, 'refunds' => 0, 'notice' => 'processed']);
})->with([
    // The notice finds the payment closed and changes nothing.
    'the expiry locks the pack first' => ['expiry', ['pack' => 'expired', 'valid_for_days' => 0, 'payment' => 'expired']],
    // The expiry finds the pack paid and changes nothing; its validity counts from the confirmation.
    'the notice locks the pack first' => ['notice', ['pack' => 'active', 'valid_for_days' => 180, 'payment' => 'succeeded']],
]);
