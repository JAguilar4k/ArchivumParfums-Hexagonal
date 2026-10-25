<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Repositories\PostgresMlReservation;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** @param Closure(): mixed $write */
function bottleSqlState(Closure $write): ?string
{
    try {
        DB::transaction(function () use ($write): void {
            $write();
        });

        return null;
    } catch (QueryException $exception) {
        return (string) $exception->getCode();
    }
}

it('rejects invalid bottle balances and statuses in PostgreSQL', function (array $changes) {
    $bottle = DecantBottleModel::factory()->create();

    expect(bottleSqlState(fn () => DB::table('decant_bottles')->where('id', $bottle->id)->update($changes)))->toBe('23514');
})->with([
    [['remaining_ml' => -1]], [['reserved_ml' => -1]], [['reserved_ml' => 101]],
    [['remaining_ml' => 101]], [['total_ml' => 0]], [['status' => 'empty']], [['status' => 'unknown']], [['remaining_ml' => 0]],
]);

it('rejects invalid movement types amounts and adjustment reasons in PostgreSQL', function (array $changes) {
    $bottle = DecantBottleModel::factory()->create();

    expect(bottleSqlState(fn () => DB::table('decant_movements')->insert([
        ...['business_id' => $bottle->business_id, 'decant_bottle_id' => $bottle->id, 'type' => 'adjustment', 'ml' => -1, 'reason' => 'Conteo'], ...$changes,
    ])))->toBe('23514');
})->with([[['type' => 'unknown']], [['ml' => 0]], [['reason' => null]], [['reason' => '  ']], [['type' => 'reservation', 'ml' => -1]]]);

it('keeps movement history append only for the application role', function (string $operation) {
    $bottle = DecantBottleModel::factory()->create();
    $this->scopeQueriesTo($bottle->business_id);
    DB::table('decant_movements')->insert(['business_id' => $bottle->business_id, 'decant_bottle_id' => $bottle->id, 'type' => 'reservation', 'ml' => 1]);

    expect(bottleSqlState(fn () => DB::statement($operation)))->toBe('42501');
    assertDatabaseCount('decant_movements', 1);
})->with(['UPDATE decant_movements SET ml = 2', 'DELETE FROM decant_movements', 'TRUNCATE decant_movements']);

it('rejects a foreign presentation and a foreign bottle through composite keys', function () {
    $first = DecantBottleModel::factory()->create();
    $second = DecantBottleModel::factory()->create();
    $this->scopeQueriesTo($first->business_id);

    expect(bottleSqlState(fn () => DB::table('decant_bottles')->where('id', $first->id)->update(['perfume_variant_id' => $second->perfume_variant_id])))->toBe('23503');
    expect(bottleSqlState(fn () => DB::table('decant_movements')->insert(['business_id' => $first->business_id, 'decant_bottle_id' => $second->id, 'type' => 'reservation', 'ml' => 1])))->toBe('23503');
});

it('forces row level security and exposes no unscoped stock or movement', function () {
    $bottle = DecantBottleModel::factory()->create();
    DB::table('decant_movements')->insert(['business_id' => $bottle->business_id, 'decant_bottle_id' => $bottle->id, 'type' => 'reservation', 'ml' => 1]);
    $this->scopeQueriesTo(null);

    foreach (['decant_bottles', 'decant_movements'] as $table) {
        $security = DB::selectOne('select relrowsecurity, relforcerowsecurity from pg_class where oid = ?::regclass', [$table]);
        expect($security->relrowsecurity)->toBeTrue();
        expect($security->relforcerowsecurity)->toBeTrue();
        assertDatabaseCount($table, 0);
    }

    expect(bottleSqlState(fn () => DB::table('decant_movements')->insert(['business_id' => $bottle->business_id, 'decant_bottle_id' => $bottle->id, 'type' => 'reservation', 'ml' => 1])))->toBe('42501');
});

it('rolls back a stock update if its movement cannot be inserted', function () {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->create(['business_id' => $business->business->id]);
    $this->scopeQueriesTo($business->business->id);

    $stock = new PostgresMlReservation(new FixedBusinessContext(new BusinessId($business->business->id)));
    expect(bottleSqlState(fn () => $stock->reserve($bottle->id, 5, userId: '0192f0c4-0000-7000-9000-000000000099')))->toBe('23503');
    assertDatabaseHas('decant_bottles', ['id' => $bottle->id, 'remaining_ml' => 100, 'reserved_ml' => 0]);
    assertDatabaseCount('decant_movements', 0);
});
