<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Src\Payments\Payments\Application\DTOs\RefundPaymentCommand;
use Src\Payments\Payments\Application\UseCases\RefundPayment;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\DatabaseRefundRepository;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Payments\HoldRegisteredRefundForCompetingRefund;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('R40: never lets two simultaneous refunds of the same payment return more than it paid', function (): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    $paymentId = (string) Str::uuid7();
    $slug = 'parallel-refund-'.Str::uuid7();

    $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $perfumeId, $variantId, $orderId, $paymentId, $slug): void {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 15000, ...$timestamps]);
        $owner->table('orders')->insert(['id' => $orderId, 'business_id' => $businessId, 'customer_id' => $customerId, 'status' => 'cancelled', 'subtotal' => 15000, 'due_now' => 7500, 'paid' => 7500, 'balance' => 7500, 'deposit_percent' => 50, 'expires_at' => now()->addMinutes(30), ...$timestamps]);
        $owner->table('payments')->insert(['id' => $paymentId, 'business_id' => $businessId, 'order_id' => $orderId, 'purpose' => 'deposit', 'amount' => 7500, 'gateway' => 'onvo', 'status' => 'succeeded', 'charged_currency' => 'CRC', 'charged_amount' => 7500, 'provider_reference' => 'session-'.$paymentId, 'capture_reference' => 'intent-'.$paymentId, ...$timestamps]);
    });

    $refund = function () use ($businessId, $paymentId): array {
        Http::preventStrayRequests();
        $queue = Queue::fake();
        app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
        app()->singleton(HoldRegisteredRefundForCompetingRefund::class, fn (): HoldRegisteredRefundForCompetingRefund => new HoldRegisteredRefundForCompetingRefund(app(DatabaseRefundRepository::class)));
        app()->bind(RefundRepository::class, fn (): HoldRegisteredRefundForCompetingRefund => app(HoldRegisteredRefundForCompetingRefund::class));
        try {
            app(RefundPayment::class)->execute(new RefundPaymentCommand($paymentId, 5000));
            $error = null;
        } catch (InvalidPaymentTransitionException $exception) {
            $error = $exception->translationKey();
        }

        return ['error' => $error, 'overlap' => app(HoldRegisteredRefundForCompetingRefund::class)->observedOverlap, 'queued' => $queue->pushed(RequestRefundJob::class)->count()];
    };

    $results = inParallel(['refunds', 'payments', 'orders', 'perfume_variants', 'perfumes', 'customers', 'users', 'businesses'], $refund, $refund);

    // The second refund waited for the lock of the payment, then counted the first one and was refused.
    expect(array_column($results, 'error'))->toEqualCanonicalizing([null, 'payments.invalid_refund'])
        ->and(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false])
        ->and(array_column($results, 'queued'))->toEqualCanonicalizing([1, 0]);
    $owner->transaction(function () use ($owner, $businessId, $paymentId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('refunds')->where('payment_id', $paymentId)->count())->toBe(1)
            ->and((int) $owner->table('refunds')->where('payment_id', $paymentId)->sum('amount'))->toBe(5000)
            ->and($owner->table('payments')->where('id', $paymentId)->first(['status', 'refunded_amount']))->toEqual((object) ['status' => 'succeeded', 'refunded_amount' => 0]);
    });
});
