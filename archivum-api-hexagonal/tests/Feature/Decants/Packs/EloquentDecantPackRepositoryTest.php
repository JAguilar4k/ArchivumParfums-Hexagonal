<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackNotFoundException;
use Src\Decants\Packs\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackPlanRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackRepository;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** A repository of packs whose business context answers the business given, as a request of that business would. */
function packRepositoryOf(string $businessId): EloquentDecantPackRepository
{
    return new EloquentDecantPackRepository(new FixedBusinessContext(new BusinessId($businessId)));
}

/** The offer Descubrimiento stored in the business, which the transaction of the test must already reach. */
function storedOfferOf(StaffedBusiness $business): DecantPackPlan
{
    $plans = new EloquentDecantPackPlanRepository(new FixedBusinessContext(new BusinessId($business->business->id)));
    $plan = DecantPackPlan::create($plans->nextId(), new BusinessId($business->business->id), new PlanName('Descubrimiento'),
        new DecantCount(5), new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180), new MaxPricePerMl(2500));
    $plans->save($plan);

    return $plan;
}

/** A pack of the offer bought by the customer of the business, not stored yet, which waits for its payment for 30 minutes. */
function packToStore(EloquentDecantPackRepository $repository, StaffedBusiness $business, DecantPackPlan $plan, ?string $key = null, ?string $customerId = null): DecantPack
{
    $customerId ??= (string) CustomerModel::query()->where('user_id', $business->customer->id)->value('id');

    return DecantPack::create($repository->nextId(), $customerId, $plan, new DateTimeImmutable('2026-11-10T15:30:00-06:00'), $key === null ? null : new IdempotencyKey($key));
}

it('stores a new pack and rebuilds an equal one, with its key or without one', function (?string $key) {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $pack = packToStore($repository, $business, storedOfferOf($business), $key);

    $repository->add($pack);

    expect($repository->find($pack->id))->toEqual($pack)
        ->and(DB::table('decant_packs')->where('id', $pack->id->value)->value('idempotency_key'))->toBe($key);
})->with(['with a key' => ['compra-1'], 'without a key' => [null]]);

it('finds the pack the customer bought with a key, and none for another key or another customer', function () {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $pack = packToStore($repository, $business, storedOfferOf($business), 'compra-1');
    $repository->add($pack);
    $otherCustomer = CustomerModel::factory()->create(['business_id' => $business->business->id])->id;

    expect($repository->findByIdempotencyKey($pack->customerId, new IdempotencyKey('compra-1')))->toEqual($pack)
        ->and($repository->findByIdempotencyKey($pack->customerId, new IdempotencyKey('compra-2')))->toBeNull()
        ->and($repository->findByIdempotencyKey($otherCustomer, new IdempotencyKey('compra-1')))->toBeNull();
});

it('refuses a second pack of the customer with the same key in its own savepoint, so the transaction goes on', function () {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $plan = storedOfferOf($business);
    $repository->add(packToStore($repository, $business, $plan, 'compra-1'));

    expect(fn () => $repository->add(packToStore($repository, $business, $plan, 'compra-1')))
        ->toThrow(IdempotencyKeyReusedException::class, 'packs.idempotency_key_reused');

    // The refused insert only undid its savepoint: the transaction still reads and writes.
    $repository->add(packToStore($repository, $business, $plan, 'compra-2'));
    assertDatabaseCount('decant_packs', 2);
});

it('lets packs without a key and the same key of another customer coexist', function () {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $plan = storedOfferOf($business);
    $otherCustomer = CustomerModel::factory()->create(['business_id' => $business->business->id])->id;

    $repository->add(packToStore($repository, $business, $plan));
    $repository->add(packToStore($repository, $business, $plan));
    $repository->add(packToStore($repository, $business, $plan, 'compra-1'));
    $repository->add(packToStore($repository, $business, $plan, 'compra-1', $otherCustomer));

    assertDatabaseCount('decant_packs', 4);
});

it('saves the status, the decants left and the expiry of a pack, and keeps what it was bought with', function () {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $pack = packToStore($repository, $business, storedOfferOf($business), 'compra-1');
    $repository->add($pack);

    $pack->activate(new DateTimeImmutable('2026-11-10T15:05:00-06:00'), new ValidityDays(180));
    $pack->redeem(5, 10000, 5, new DateTimeImmutable('2026-11-11T10:00:00-06:00'));
    $repository->save($pack);

    $stored = $repository->find($pack->id);
    expect($stored?->status)->toBe(DecantPackStatus::Active)
        ->and($stored?->remainingDecants)->toBe(4)
        ->and($stored?->expiresAt)->toEqual(new DateTimeImmutable('2027-05-09T15:05:00-06:00'))
        ->and($stored?->idempotencyKey?->value)->toBe('compra-1')
        ->and([$stored?->totalDecants, $stored?->mlPerDecant, $stored?->maxPricePerMl?->amount])->toBe([5, 5, 2500]);
});

it('locks the pack it returns for an update, and only then', function () {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $pack = packToStore($repository, $business, storedOfferOf($business));
    $repository->add($pack);

    DB::enableQueryLog();
    $locked = $repository->find($pack->id, forUpdate: true);
    $read = $repository->find($pack->id);
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    expect($locked)->toEqual($pack)
        ->and($read)->toEqual($pack)
        ->and($queries)->toHaveCount(2)
        ->and($queries[0])->toEndWith('for update')
        ->and($queries[1])->not->toContain('for update');
});

it('finds no pack of another business and refuses to store one there, as for a pack that does not exist', function () {
    $mine = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $this->scopeQueriesTo($other->business->id);
    $theirRepository = packRepositoryOf($other->business->id);
    $theirs = packToStore($theirRepository, $other, storedOfferOf($other), 'compra-1');
    $theirRepository->add($theirs);
    $myRepository = packRepositoryOf($mine->business->id);

    // The database still reaches the other business: only the filter of the repository hides its pack.
    expect($myRepository->find($theirs->id))->toBeNull()
        ->and($myRepository->findByIdempotencyKey($theirs->customerId, new IdempotencyKey('compra-1')))->toBeNull()
        ->and(fn () => $myRepository->add($theirs))->toThrow(DecantPackNotFoundException::class, 'packs.pack_not_found')
        ->and(fn () => $myRepository->save($theirs))->toThrow(DecantPackNotFoundException::class, 'packs.pack_not_found');
});

it('R34: keeps one redemption for each saved line of an order, and forgets it only once', function () {
    $business = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $pack = packToStore($repository, $business, storedOfferOf($business));
    $repository->add($pack);
    $line = (string) OrderLineModel::query()->where('order_id', StoredOrders::create($business, 'confirmed', false, true)['order']->id)->value('id');

    $repository->addRedemption($pack->id, $line);

    assertDatabaseHas('decant_pack_redemptions', ['business_id' => $business->business->id, 'decant_pack_id' => $pack->id->value, 'order_line_id' => $line]);
    expect(fn () => DB::transaction(fn () => $repository->addRedemption($pack->id, $line)))->toThrow(UniqueConstraintViolationException::class)
        ->and($repository->removeRedemption($pack->id, $line))->toBeTrue()
        ->and($repository->removeRedemption($pack->id, $line))->toBeFalse();
    assertDatabaseCount('decant_pack_redemptions', 0);
});

it('forgets no redemption of another pack or of another business', function () {
    $business = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $this->scopeQueriesTo($business->business->id);
    $repository = packRepositoryOf($business->business->id);
    $pack = packToStore($repository, $business, storedOfferOf($business));
    $repository->add($pack);
    $line = (string) OrderLineModel::query()->where('order_id', StoredOrders::create($business, 'confirmed', false, true)['order']->id)->value('id');
    $repository->addRedemption($pack->id, $line);

    expect($repository->removeRedemption(new DecantPackId('0192f0c4-0000-7000-9000-000000009999'), $line))->toBeFalse()
        ->and(packRepositoryOf($other->business->id)->removeRedemption($pack->id, $line))->toBeFalse();
    assertDatabaseCount('decant_pack_redemptions', 1);
});
