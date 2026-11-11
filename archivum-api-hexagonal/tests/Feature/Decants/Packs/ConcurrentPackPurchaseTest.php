<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Src\Decants\Packs\Application\DTOs\PurchaseDecantPackCommand;
use Src\Decants\Packs\Application\UseCases\PurchaseDecantPack;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackRepository;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\HoldPackForCompetingPurchase;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('buys one pack with one payment and one charge when the same purchase arrives twice at once in two PostgreSQL processes', function (bool $alreadyCustomer): void {
    $owner = DB::connection('pgsql_admin');
    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $planId = (string) Str::uuid7();
    $slug = 'parallel-pack-'.Str::uuid7();
    $owner->transaction(function () use ($owner, $businessId, $userId, $planId, $slug, $alreadyCustomer): void {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('decant_pack_plans')->insert(['id' => $planId, 'business_id' => $businessId, 'name' => 'Descubrimiento', 'decants_count' => 5, 'ml_per_decant' => 5,
            'price' => 40000, 'validity_days' => 180, 'max_price_per_ml' => 2500, ...$timestamps]);
        if ($alreadyCustomer) {
            $owner->table('customers')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        }
    });

    // Both requests double-click the same purchase with the same key.
    $operation = function () use ($businessId, $userId, $planId): array {
        Http::preventStrayRequests();
        config(['payments.onvo.secret_key' => 'onvo_test_fake']);
        Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => fn (Request $request): PromiseInterface => Http::response([
            'id' => 'session-'.$request['metadata']['paymentId'], 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-'.$request['metadata']['paymentId'],
        ], 201)]);
        app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
        app()->singleton(HoldPackForCompetingPurchase::class, fn (): HoldPackForCompetingPurchase => new HoldPackForCompetingPurchase(app(EloquentDecantPackRepository::class)));
        app()->bind(DecantPackRepository::class, fn (): HoldPackForCompetingPurchase => app(HoldPackForCompetingPurchase::class));
        $purchased = app(PurchaseDecantPack::class)->execute(new PurchaseDecantPackCommand($planId, $userId, 'doble-clic', 'card', now()->toIso8601String()));

        return ['pack' => $purchased->pack->id->value, 'created' => $purchased->created, 'payment' => $purchased->payment?->id,
            'requests' => Http::recorded()->count(), 'overlap' => app(HoldPackForCompetingPurchase::class)->observedOverlap];
    };

    $results = inParallel(['payments', 'decant_packs', 'decant_pack_plans', 'customers', 'users', 'businesses'], $operation, $operation);

    expect(array_unique(array_column($results, 'pack')))->toHaveCount(1)
        ->and(array_column($results, 'created'))->toEqualCanonicalizing([true, false])
        ->and(array_unique(array_column($results, 'payment')))->toHaveCount(1)
        ->and(array_sum(array_column($results, 'requests')))->toBe(1)
        // The second request waited for the first one inside its transaction: the race was real. For a customer of the
        // store it waited on the unique index of the key; for a new account, already on the one of its customer record.
        ->and(array_column($results, 'overlap'))->toEqualCanonicalizing([true, false]);
    $owner->transaction(function () use ($owner, $businessId, $userId): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);
        expect($owner->table('customers')->where('user_id', $userId)->count())->toBe(1)
            ->and($owner->table('decant_packs')->where('idempotency_key', 'doble-clic')->count())->toBe(1)
            ->and($owner->table('decant_packs')->count())->toBe(1)
            ->and($owner->table('payments')->whereNotNull('decant_pack_id')->count())->toBe(1);
    });
})->with([
    'a customer of the store' => [true],
    'an account that is not a customer of the store yet' => [false],
]);
