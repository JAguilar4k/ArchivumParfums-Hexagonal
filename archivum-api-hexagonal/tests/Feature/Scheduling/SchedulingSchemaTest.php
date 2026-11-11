<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function (): void {
    TwoBusinesses::create();
});

/**
 * Insert a service of the business the queries reach, with every column the schema requires.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertSchedulingService(string $businessId, array $overrides = []): void
{
    DB::table('services')->insert([
        'business_id' => $businessId,
        'name' => 'Prueba de olores',
        'duration_minutes' => 45,
        'price' => 0,
        'buffer_minutes' => 15,
        'max_perfumes' => 5,
        ...$overrides,
    ]);
}

/**
 * Run a write in a savepoint and return the error PostgreSQL answered, or null when it accepted the row.
 *
 * @param  Closure(): mixed  $write
 */
function schedulingWriteError(Closure $write): ?QueryException
{
    try {
        DB::transaction(fn (): mixed => $write());
    } catch (QueryException $exception) {
        return $exception;
    }

    return null;
}

test('rejects a service outside the range of each of its CHECK constraints', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    $cases = [
        'services_duration_minutes_check' => ['duration_minutes' => 0],
        'services_price_check' => ['price' => -1],
        'services_buffer_minutes_check' => ['buffer_minutes' => -1],
        'services_max_perfumes_check' => ['max_perfumes' => -1],
    ];

    foreach ($cases as $constraint => $override) {
        expect(fn () => DB::transaction(fn () => insertSchedulingService($businessId, $override)))
            ->toThrow(QueryException::class, $constraint);
    }

    expect(DB::table('services')->count())->toBe(0);

    insertSchedulingService($businessId);

    expect(DB::table('services')->count())->toBe(1);
});

test('rejects overlapping opening hours but accepts contiguous ones', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 2, 'opens_at' => '17:00', 'closes_at' => '20:00',
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 2, 'opens_at' => '19:00', 'closes_at' => '21:00',
    ])))->toThrow(QueryException::class, 'business_hours_no_overlap_excl');

    DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 2, 'opens_at' => '20:00', 'closes_at' => '22:00',
    ]);

    expect(DB::table('business_hours')->where('business_id', $businessId)->count())->toBe(2);
});

test('rejects an opening tramo that ends before it starts or on a weekday outside one to seven', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    expect(fn () => DB::transaction(fn () => DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 3, 'opens_at' => '09:00', 'closes_at' => '09:00',
    ])))->toThrow(QueryException::class, 'business_hours_opens_at_check');

    foreach ([0, 8] as $weekday) {
        expect(fn () => DB::transaction(fn () => DB::table('business_hours')->insert([
            'business_id' => $businessId, 'weekday' => $weekday, 'opens_at' => '09:00', 'closes_at' => '10:00',
        ])))->toThrow(QueryException::class, 'business_hours_weekday_check');
    }
});

test('rejects overlapping working hours of the same person but accepts contiguous ones', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    $memberId = (string) DB::table('members')->where('business_id', $businessId)->value('id');

    DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 4, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 4, 'starts_at' => '10:00', 'ends_at' => '14:00',
    ])))->toThrow(QueryException::class, 'staff_schedules_no_overlap_excl');

    DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 4, 'starts_at' => '12:00', 'ends_at' => '16:00',
    ]);

    // The same hours on another weekday are another tramo.
    DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 5, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ]);

    expect(DB::table('staff_schedules')->where('business_id', $businessId)->count())->toBe(3);
});

test('rejects a working tramo that ends before it starts', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    $memberId = (string) DB::table('members')->where('business_id', $businessId)->value('id');

    expect(fn () => DB::transaction(fn () => DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '11:59',
    ])))->toThrow(QueryException::class, 'staff_schedules_starts_at_check');
});

test('rejects a block that ends before it starts or whose source is not manual or google', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    expect(fn () => DB::transaction(fn () => DB::table('schedule_blocks')->insert([
        'business_id' => $businessId, 'starts_at' => now(), 'ends_at' => now()->subHour(), 'reason' => 'Inventario',
    ])))->toThrow(QueryException::class, 'schedule_blocks_starts_at_check');

    expect(fn () => DB::transaction(fn () => DB::table('schedule_blocks')->insert([
        'business_id' => $businessId, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Inventario', 'source' => 'otro',
    ])))->toThrow(QueryException::class, 'schedule_blocks_source_check');
});

test('accepts a block of the whole business, without a member, with the default manual source', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    DB::table('schedule_blocks')->insert([
        'business_id' => $businessId, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Inventario',
    ]);

    $block = DB::table('schedule_blocks')->where('business_id', $businessId)->sole();

    expect($block->member_id)->toBeNull()
        ->and($block->source)->toBe('manual');
});

test('refuses a member of another business in the working hours and in the blocks', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    $this->scopeQueriesTo($second);
    $memberOfSecond = (string) DB::table('members')->where('business_id', $second)->value('id');

    $this->scopeQueriesTo($first);

    expect(fn () => DB::transaction(fn () => DB::table('staff_schedules')->insert([
        'business_id' => $first, 'member_id' => $memberOfSecond, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => DB::table('schedule_blocks')->insert([
        'business_id' => $first, 'member_id' => $memberOfSecond, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Inventario',
    ])))->toThrow(QueryException::class);
});

test('keeps the rows of another business out of the four scheduling tables with the application role', function (string $table): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    $this->scopeQueriesTo($second);
    $memberOfSecond = (string) DB::table('members')->where('business_id', $second)->value('id');
    $this->scopeQueriesTo($first);
    $memberOfFirst = (string) DB::table('members')->where('business_id', $first)->value('id');

    /** One valid row of each table, written for the business and the member received. */
    $rowFor = match ($table) {
        'services' => fn (string $businessId, string $memberId): array => [
            'business_id' => $businessId, 'name' => 'Prueba de olores', 'duration_minutes' => 45,
            'price' => 0, 'buffer_minutes' => 15, 'max_perfumes' => 5,
        ],
        'business_hours' => fn (string $businessId, string $memberId): array => [
            'business_id' => $businessId, 'weekday' => 1, 'opens_at' => '17:00', 'closes_at' => '20:00',
        ],
        'staff_schedules' => fn (string $businessId, string $memberId): array => [
            'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 1, 'starts_at' => '17:00', 'ends_at' => '20:00',
        ],
        'schedule_blocks' => fn (string $businessId, string $memberId): array => [
            'business_id' => $businessId, 'member_id' => null, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Inventario',
        ],
        default => throw new InvalidArgumentException("Unknown scheduling table [{$table}]."),
    };

    DB::table($table)->insert($rowFor($first, $memberOfFirst));

    expect(DB::table($table)->pluck('business_id')->all())->toBe([$first]);

    // A row of the other business is refused by the policy of the row, not by the code of the application.
    $error = schedulingWriteError(fn () => DB::table($table)->insert($rowFor($second, $memberOfSecond)));

    expect($error?->getCode())->toBe('42501')
        ->and(DB::table($table)->pluck('business_id')->all())->toBe([$first]);
})->with(['services', 'business_hours', 'staff_schedules', 'schedule_blocks']);
