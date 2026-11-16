<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresDecantPackBalance;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const PACK_BALANCE_AT = '2026-12-01T10:00:00-06:00';

/** The balance of the packs whose business context answers the business given, as a request of that business would. */
function packBalanceOf(string $businessId): PostgresDecantPackBalance
{
    return new PostgresDecantPackBalance(new FixedBusinessContext(new BusinessId($businessId)));
}

/**
 * The decants left and the status of a stored pack.
 *
 * @return array{int, string}
 */
function packBalanceRow(string $packId): array
{
    $row = DB::table('decant_packs')->where('id', $packId)->first(['remaining_decants', 'status']);

    return [(int) $row?->remaining_decants, (string) $row?->status];
}

it('R34: takes one decant of an active pack within its validity, and exhausts it with the last one in the same statement', function () {
    $business = StaffedBusiness::create();
    $packId = StoredPacks::create($business, ['status' => 'active', 'remaining_decants' => 2, 'expires_at' => '2027-05-09T15:05:00-06:00'])['pack_id'];
    $this->scopeQueriesTo($business->business->id);
    $balance = packBalanceOf($business->business->id);
    $at = new DateTimeImmutable(PACK_BALANCE_AT);

    expect($balance->take(new DecantPackId($packId), $at))->toBeTrue()
        ->and(packBalanceRow($packId))->toBe([1, 'active'])
        ->and($balance->take(new DecantPackId($packId), $at))->toBeTrue()
        ->and(packBalanceRow($packId))->toBe([0, 'exhausted'])
        ->and($balance->take(new DecantPackId($packId), $at))->toBeFalse()
        ->and(packBalanceRow($packId))->toBe([0, 'exhausted']);
});

it('R35: takes no decant of a pack that waits for its payment, expired or whose validity ends at that instant', function (array $pack) {
    $business = StaffedBusiness::create();
    $packId = StoredPacks::create($business, $pack)['pack_id'];
    $this->scopeQueriesTo($business->business->id);

    expect(packBalanceOf($business->business->id)->take(new DecantPackId($packId), new DateTimeImmutable(PACK_BALANCE_AT)))->toBeFalse()
        ->and(packBalanceRow($packId))->toBe([$pack['remaining_decants'], $pack['status']]);
})->with([
    'waiting for its payment' => [['status' => 'pending_payment', 'remaining_decants' => 5, 'expires_at' => '2026-12-01T10:30:00-06:00']],
    'expired by the queue' => [['status' => 'expired', 'remaining_decants' => 3, 'expires_at' => '2026-11-30T10:00:00-06:00']],
    'active until that instant' => [['status' => 'active', 'remaining_decants' => 3, 'expires_at' => PACK_BALANCE_AT]],
]);

it('takes no decant of a pack of another business, as for a pack that does not exist', function () {
    $mine = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $theirs = StoredPacks::create($other, ['status' => 'active', 'remaining_decants' => 5, 'expires_at' => '2027-05-09T15:05:00-06:00'])['pack_id'];
    // The database still reaches the other business: only the filter of the adapter keeps its pack out.
    $this->scopeQueriesTo($other->business->id);
    $balance = packBalanceOf($mine->business->id);
    $at = new DateTimeImmutable(PACK_BALANCE_AT);

    expect($balance->take(new DecantPackId($theirs), $at))->toBeFalse()
        ->and($balance->take(new DecantPackId('0192f0c4-0000-7000-9000-000000009999'), $at))->toBeFalse()
        ->and(packBalanceRow($theirs))->toBe([5, 'active']);
});

it('R34: leaves the balance at 0 when a path skips the condition, because the CHECK of the table refuses it', function () {
    $business = StaffedBusiness::create();
    $packId = StoredPacks::create($business, ['status' => 'active', 'remaining_decants' => 1, 'expires_at' => '2027-05-09T15:05:00-06:00'])['pack_id'];
    $this->scopeQueriesTo($business->business->id);
    packBalanceOf($business->business->id)->take(new DecantPackId($packId), new DateTimeImmutable(PACK_BALANCE_AT));

    expect(fn () => DB::transaction(fn (): int => DB::update('UPDATE decant_packs SET remaining_decants = remaining_decants - 1 WHERE id = ?', [$packId])))
        ->toThrow(QueryException::class, 'decant_packs_remaining_decants_check')
        ->and(packBalanceRow($packId))->toBe([0, 'exhausted']);
});
