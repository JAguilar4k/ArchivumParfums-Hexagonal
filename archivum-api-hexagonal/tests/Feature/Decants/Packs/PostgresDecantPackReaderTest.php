<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Application\DTOs\SoldPackView;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresDecantPackReader;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** The moment of every reading of this file. */
const PACK_READER_AT = '2026-11-20T10:00:00-06:00';

/** A reader of packs whose business context answers the business given, as a request of that business would. */
function packReaderOf(string $businessId): PostgresDecantPackReader
{
    return new PostgresDecantPackReader(new FixedBusinessContext(new BusinessId($businessId)));
}

/**
 * The identifiers of some packs read, in the order read.
 *
 * @param  list<PurchasedPackView|SoldPackView>  $packs
 * @return list<string>
 */
function readPackIds(array $packs): array
{
    return array_map(fn (PurchasedPackView|SoldPackView $pack): string => $pack->id, $packs);
}

it('reads the packs an account bought in the business of the context, newest first, with their store and their offer', function () {
    $business = BusinessModel::factory()->create(['name' => 'Perfumería Primera', 'slug' => 'perfumeria-primera']);
    $other = BusinessModel::factory()->create();
    $account = UserModel::factory()->create();
    $customer = CustomerModel::factory()->create(['business_id' => $business->id, 'user_id' => $account->id]);
    $plan = DecantPackPlanModel::factory()->create(['business_id' => $business->id, 'name' => 'Descubrimiento']);
    $older = DecantPackModel::factory()->create(['business_id' => $business->id, 'customer_id' => $customer->id, 'decant_pack_plan_id' => $plan->id,
        'remaining_decants' => 2, 'expires_at' => '2027-05-01T10:00:00-06:00', 'created_at' => '2026-11-01T09:00:00-06:00']);
    $newer = DecantPackModel::factory()->pendingPayment()->create(['business_id' => $business->id, 'customer_id' => $customer->id, 'decant_pack_plan_id' => $plan->id,
        'expires_at' => '2026-11-20T10:30:00-06:00', 'created_at' => '2026-11-20T09:00:00-06:00']);
    DecantPackModel::factory()->create(['business_id' => $business->id]);
    DecantPackModel::factory()->create(['business_id' => $other->id, 'customer_id' => CustomerModel::factory()->create(['business_id' => $other->id, 'user_id' => $account->id])->id]);
    $this->scopeQueriesTo($business->id);

    expect(packReaderOf($business->id)->ofAccount($account->id, new DateTimeImmutable(PACK_READER_AT)))->toEqual([
        new PurchasedPackView($newer->id, $business->id, 'Perfumería Primera', 'perfumeria-primera', $plan->id, 'Descubrimiento', 5, 5, 5,
            'pending_payment', '2026-11-20T10:30:00-06:00', '2026-11-20T09:00:00-06:00'),
        new PurchasedPackView($older->id, $business->id, 'Perfumería Primera', 'perfumeria-primera', $plan->id, 'Descubrimiento', 5, 2, 5,
            'active', '2027-05-01T10:00:00-06:00', '2026-11-01T09:00:00-06:00'),
    ]);
});

it('R35: gives each pack the status the domain gives it at the moment of the reading, also before the queue expires it', function (string $status, int $remaining, string $expiresAt, DecantPackStatus $expected) {
    $pack = DecantPackModel::factory()->create(['status' => $status, 'remaining_decants' => $remaining, 'expires_at' => $expiresAt]);
    $this->scopeQueriesTo($pack->business_id);
    $at = new DateTimeImmutable(PACK_READER_AT);
    $reader = packReaderOf($pack->business_id);
    $account = CustomerModel::query()->whereKey($pack->customer_id)->sole()->user_id;
    $domain = new EloquentDecantPackRepository(new FixedBusinessContext(new BusinessId($pack->business_id)))->find(new DecantPackId($pack->id));

    expect($reader->ofAccount($account, $at)[0]->status)->toBe($expected->value)
        ->and($reader->sold(null, $at, 1, 20)->items[0]->status)->toBe($expected->value)
        ->and($domain?->statusAt($at))->toBe($expected);
})->with([
    'waiting for its payment within its deadline' => ['pending_payment', 5, '2026-11-20T10:30:00-06:00', DecantPackStatus::PendingPayment],
    'waiting for its payment after its deadline' => ['pending_payment', 5, '2026-11-20T09:30:00-06:00', DecantPackStatus::Expired],
    'active within its validity' => ['active', 3, '2026-11-20T10:00:01-06:00', DecantPackStatus::Active],
    'active at the exact end of its validity' => ['active', 3, '2026-11-20T10:00:00-06:00', DecantPackStatus::Expired],
    'active after its validity, before the queue expires it' => ['active', 3, '2026-11-19T10:00:00-06:00', DecantPackStatus::Expired],
    'exhausted, even after its validity' => ['exhausted', 0, '2026-11-19T10:00:00-06:00', DecantPackStatus::Exhausted],
    'expired by the queue' => ['expired', 3, '2026-11-19T10:00:00-06:00', DecantPackStatus::Expired],
]);

it('pages the packs sold in the business of the context, newest first, and keeps the ones with the status asked at that moment', function () {
    $business = BusinessModel::factory()->create();
    $plan = DecantPackPlanModel::factory()->create(['business_id' => $business->id, 'name' => 'Descubrimiento']);
    $customer = CustomerModel::factory()->create(['business_id' => $business->id, 'user_id' => UserModel::factory()->create(['name' => 'Mariana Rojas'])->id]);
    $sold = fn (string $status, int $remaining, string $expiresAt, string $boughtAt): string => DecantPackModel::factory()->create([
        'business_id' => $business->id, 'customer_id' => $customer->id, 'decant_pack_plan_id' => $plan->id,
        'status' => $status, 'remaining_decants' => $remaining, 'expires_at' => $expiresAt, 'created_at' => $boughtAt,
    ])->id;
    $expiredByTheQueue = $sold('expired', 3, '2026-11-10T10:00:00-06:00', '2026-05-10T10:00:00-06:00');
    $overdue = $sold('active', 2, '2026-11-20T09:00:00-06:00', '2026-05-20T10:00:00-06:00');
    $active = $sold('active', 4, '2027-05-01T10:00:00-06:00', '2026-11-01T10:00:00-06:00');
    $exhausted = $sold('exhausted', 0, '2027-05-02T10:00:00-06:00', '2026-11-02T10:00:00-06:00');
    $unpaidOverdue = $sold('pending_payment', 5, '2026-11-19T10:30:00-06:00', '2026-11-19T10:00:00-06:00');
    $this->scopeQueriesTo($business->id);
    $reader = packReaderOf($business->id);
    $at = new DateTimeImmutable(PACK_READER_AT);

    $all = $reader->sold(null, $at, 1, 20);
    $expired = $reader->sold(DecantPackStatus::Expired, $at, 1, 2);

    expect(readPackIds($all->items))->toBe([$unpaidOverdue, $exhausted, $active, $overdue, $expiredByTheQueue])
        ->and($all->items[1])->toEqual(new SoldPackView($exhausted, 'Mariana Rojas', $plan->id, 'Descubrimiento', 5, 0, 5, 'exhausted', '2027-05-02T10:00:00-06:00'))
        ->and(readPackIds($expired->items))->toBe([$unpaidOverdue, $overdue])
        ->and([$expired->total, $expired->page, $expired->perPage])->toBe([3, 1, 2])
        ->and(readPackIds($reader->sold(DecantPackStatus::Expired, $at, 2, 2)->items))->toBe([$expiredByTheQueue])
        ->and(readPackIds($reader->sold(DecantPackStatus::Active, $at, 1, 20)->items))->toBe([$active])
        ->and(readPackIds($reader->sold(DecantPackStatus::Exhausted, $at, 1, 20)->items))->toBe([$exhausted])
        ->and($reader->sold(DecantPackStatus::PendingPayment, $at, 1, 20)->total)->toBe(0);
});

it('reads only the packs of the business of the context, whatever the database is scoped to', function () {
    $account = UserModel::factory()->create();
    $mine = DecantPackModel::factory()->create(['customer_id' => fn (array $attributes): string => CustomerModel::factory()->create(['business_id' => $attributes['business_id'], 'user_id' => $account->id])->id]);
    $other = DecantPackModel::factory()->create(['customer_id' => fn (array $attributes): string => CustomerModel::factory()->create(['business_id' => $attributes['business_id'], 'user_id' => $account->id])->id]);
    $reader = packReaderOf($mine->business_id);
    $at = new DateTimeImmutable(PACK_READER_AT);

    // Scoped to the other business, only the filter of the reader keeps the packs of that business away.
    $this->scopeQueriesTo($other->business_id);

    expect($reader->ofAccount($account->id, $at))->toBe([])
        ->and($reader->sold(null, $at, 1, 20)->items)->toBe([])
        ->and($reader->sold(null, $at, 1, 20)->total)->toBe(0);

    // Scoped to the right business, as each request scopes it, it reads the pack of that business.
    $this->scopeQueriesTo($mine->business_id);

    expect(readPackIds($reader->ofAccount($account->id, $at)))->toBe([$mine->id])
        ->and(readPackIds($reader->sold(null, $at, 1, 20)->items))->toBe([$mine->id]);
});
