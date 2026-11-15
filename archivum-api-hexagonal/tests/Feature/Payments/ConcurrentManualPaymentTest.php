<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Tests\Support\Payments\HoldInitialPaymentForCompetingRequest;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('R29: accepts one of two simultaneous records of the same balance and never takes more than it', function (): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $ownerId = (string) Str::uuid7();
    $customerUserId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    $plainToken = 'fictitious-manual-payment-token-'.Str::uuid7();
    $slug = 'parallel-manual-payment-'.Str::uuid7();

    $tokenId = $owner->transaction(function () use ($owner, $businessId, $ownerId, $customerUserId, $customerId, $perfumeId, $variantId, $orderId, $plainToken, $slug): int {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert([
            ['id' => $ownerId, 'name' => 'Dueña ficticia', 'email' => 'owner-'.$slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps],
            ['id' => $customerUserId, 'name' => 'Cliente ficticio', 'email' => 'customer-'.$slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps],
        ]);
        $owner->table('members')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'user_id' => $ownerId, 'role' => 'owner', 'attends_appointments' => true, ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $customerUserId, ...$timestamps]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma de prueba', ...$timestamps]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 15000, ...$timestamps]);
        $owner->table('orders')->insert(['id' => $orderId, 'business_id' => $businessId, 'customer_id' => $customerId, 'status' => 'ready', 'subtotal' => 15000, 'due_now' => 7500, 'paid' => 7500, 'balance' => 7500, 'deposit_percent' => 50, 'expires_at' => now()->addMinutes(30), ...$timestamps]);
        $owner->table('order_lines')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'order_id' => $orderId, 'kind' => 'bottle', 'perfume_variant_id' => $variantId, 'quantity' => 1, 'unit_price' => 15000, 'status' => 'received', ...$timestamps]);

        return (int) $owner->table('personal_access_tokens')->insertGetId(['tokenable_type' => UserModel::class, 'tokenable_id' => $ownerId, 'name' => 'concurrency-test', 'token' => hash('sha256', $plainToken), 'abilities' => json_encode(Ability::namesGrantedTo(AccountRole::Owner), JSON_THROW_ON_ERROR), ...$timestamps]);
    });
    $token = $tokenId.'|'.$plainToken;

    $request = function () use ($orderId, $token): array {
        app()->singleton(HoldInitialPaymentForCompetingRequest::class, fn (): HoldInitialPaymentForCompetingRequest => new HoldInitialPaymentForCompetingRequest(app(EloquentPaymentRepository::class)));
        app()->bind(PaymentRepository::class, fn (): HoldInitialPaymentForCompetingRequest => app(HoldInitialPaymentForCompetingRequest::class));
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/panel/pedidos/'.$orderId.'/pagos-manuales', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['metodo' => 'efectivo', 'monto' => 7500, 'referencia' => 'Recibo 1'], JSON_THROW_ON_ERROR));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return ['status' => $response->getStatusCode(), 'overlap' => app(HoldInitialPaymentForCompetingRequest::class)->observedOverlap];
    };

    $results = inParallel(['payments', 'order_lines', 'orders', 'perfume_variants', 'perfumes', 'customers', 'members', 'personal_access_tokens', 'users', 'businesses'], $request, $request);

    // The second record waited for the lock of the first one on the order and then found no balance left.
    expect(array_column($results, 'status'))->toEqualCanonicalizing([201, 409])
        ->and(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false]);
    $owner->transaction(function () use ($owner, $businessId, $orderId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('payments')->where('order_id', $orderId)->count())->toBe(1)
            ->and($owner->table('orders')->where('id', $orderId)->first(['paid', 'balance']))->toEqual((object) ['paid' => 15000, 'balance' => 0]);
    });
});
