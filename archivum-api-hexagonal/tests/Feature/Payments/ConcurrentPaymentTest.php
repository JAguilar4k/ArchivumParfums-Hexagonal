<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Src\Payments\Payments\Application\UseCases\StartPayment;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Payments\HoldInitialPaymentForCompetingRequest;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('creates one remote charge and returns one durable payment to simultaneous PostgreSQL processes', function (): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $slug = 'parallel-payment-'.Str::uuid7();
    $owner->transaction(function () use ($owner, $businessId, $userId, $customerId, $orderId, $perfumeId, $variantId, $slug): void {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 15000, ...$timestamps]);
        $owner->table('orders')->insert(['id' => $orderId, 'business_id' => $businessId, 'customer_id' => $customerId, 'status' => 'pending_payment', 'subtotal' => 15000, 'due_now' => 7500, 'paid' => 0, 'balance' => 7500, 'deposit_percent' => 50, 'expires_at' => now()->addMinutes(30), ...$timestamps]);
        $owner->table('order_lines')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'order_id' => $orderId, 'kind' => 'bottle', 'perfume_variant_id' => $variantId, 'quantity' => 1, 'unit_price' => 15000, 'status' => 'pending', ...$timestamps]);
    });

    $operation = function () use ($businessId, $userId, $orderId): array {
        Http::preventStrayRequests();
        config(['payments.onvo.secret_key' => 'onvo_test_fake']);
        $committedAttemptVisible = false;
        Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => function () use ($businessId, $orderId, &$committedAttemptVisible): PromiseInterface {
            $otherConnection = DB::connection('pgsql_admin');
            $committedAttemptVisible = $otherConnection->transaction(function () use ($otherConnection, $businessId, $orderId): bool {
                $otherConnection->statement("select set_config('app.business_id', ?, true)", [$businessId]);

                return $otherConnection->table('payments')->where('order_id', $orderId)->count() === 1;
            });

            return Http::response(['id' => 'single-session', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/single-session'], 201);
        }]);
        app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
        app()->singleton(HoldInitialPaymentForCompetingRequest::class, fn (): HoldInitialPaymentForCompetingRequest => new HoldInitialPaymentForCompetingRequest(app(EloquentPaymentRepository::class)));
        app()->bind(PaymentRepository::class, fn (): HoldInitialPaymentForCompetingRequest => app(HoldInitialPaymentForCompetingRequest::class));
        $result = app(StartPayment::class)->execute($orderId, $userId);

        return ['id' => $result->id, 'created' => $result->created, 'requests' => Http::recorded()->count(), 'committed_before_post' => $committedAttemptVisible, 'overlap' => app(HoldInitialPaymentForCompetingRequest::class)->observedOverlap];
    };

    $results = inParallel(['payments', 'orders', 'order_lines', 'perfume_variants', 'perfumes', 'customers', 'users', 'businesses'], $operation, $operation);

    expect(array_unique(array_column($results, 'id')))->toHaveCount(1);
    expect(array_column($results, 'created'))->toEqualCanonicalizing([true, false]);
    expect(array_sum(array_column($results, 'requests')))->toBe(1);
    expect(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false]);
    expect(array_column($results, 'committed_before_post'))->toEqualCanonicalizing([true, false]);
    $owner->transaction(function () use ($owner, $businessId, $orderId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('payments')->where('order_id', $orderId)->count())->toBe(1);
        expect($owner->table('orders')->where('id', $orderId)->value('paid'))->toBe(0);
    });
});
