<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/**
 * Create a business with the rows an appointment points at, pinned to its context, and the ids the tests insert with.
 *
 * @return array{business: string, service: string, customer: string, member: string, perfume: string}
 */
function appointmentScenario(): array
{
    $business = BusinessModel::factory()->create();

    // Pin the context of the business before creating the rows the row-level policies protect.
    DB::statement('select set_config(?, ?, true)', ['app.business_id', $business->id]);

    return [
        'business' => $business->id,
        'service' => ServiceModel::factory()->create(['business_id' => $business->id])->id,
        'customer' => CustomerModel::factory()->create(['business_id' => $business->id])->id,
        'member' => MemberModel::factory()->create(['business_id' => $business->id])->id,
        'perfume' => PerfumeModel::factory()->create(['business_id' => $business->id])->id,
    ];
}

/**
 * Insert an appointment of the scenario: 10:00 local, a 45 minute service and 15 minutes of margin.
 *
 * @param  array{business: string, service: string, customer: string, member: string, perfume: string}  $ids
 * @param  array<string, mixed>  $overrides
 */
function insertAppointment(array $ids, array $overrides = []): void
{
    DB::table('appointments')->insert([
        'business_id' => $ids['business'],
        'service_id' => $ids['service'],
        'customer_id' => $ids['customer'],
        'member_id' => $ids['member'],
        'starts_at' => '2026-12-01 10:00:00-06',
        'ends_at' => '2026-12-01 10:45:00-06',
        'blocked_until' => '2026-12-01 11:00:00-06',
        'status' => 'confirmed',
        ...$overrides,
    ]);
}

/**
 * Run a write in a savepoint and return the error PostgreSQL answered, or null when it accepted the row.
 *
 * @param  Closure(): mixed  $write
 */
function appointmentWriteError(Closure $write): ?QueryException
{
    try {
        DB::transaction(fn (): mixed => $write());
    } catch (QueryException $exception) {
        return $exception;
    }

    return null;
}

test('rejects an appointment outside the range of each of its CHECK constraints', function (): void {
    $ids = appointmentScenario();

    $cases = [
        'appointments_ends_at_check' => ['ends_at' => '2026-12-01 10:00:00-06'],
        'appointments_blocked_until_check' => ['blocked_until' => '2026-12-01 10:30:00-06'],
        'appointments_status_check' => ['status' => 'unknown'],
        'appointments_hold_expires_at_check' => ['status' => 'held'],
    ];

    foreach ($cases as $constraint => $override) {
        expect(fn () => DB::transaction(fn () => insertAppointment($ids, $override)))
            ->toThrow(QueryException::class, $constraint);
    }

    expect(DB::table('appointments')->count())->toBe(0);

    insertAppointment($ids, ['status' => 'held', 'hold_expires_at' => '2026-12-01 09:00:00-06']);

    expect(DB::table('appointments')->count())->toBe(1);
});

test('rejects two active appointments of the same person that overlap and accepts a contiguous one', function (): void {
    $ids = appointmentScenario();

    insertAppointment($ids);

    // The margin of the first one blocks until 11:00, so an appointment inside it breaks the exclusion.
    expect(fn () => DB::transaction(fn () => insertAppointment($ids, [
        'starts_at' => '2026-12-01 10:30:00-06',
        'ends_at' => '2026-12-01 11:15:00-06',
        'blocked_until' => '2026-12-01 11:30:00-06',
    ])))->toThrow(QueryException::class, 'appointments_member_slot_exclusion');

    // The range is half open: starting exactly when the margin ends is allowed.
    insertAppointment($ids, [
        'starts_at' => '2026-12-01 11:00:00-06',
        'ends_at' => '2026-12-01 11:45:00-06',
        'blocked_until' => '2026-12-01 12:00:00-06',
    ]);

    expect(DB::table('appointments')->count())->toBe(2);
});

test('accepts the same slot for another person and over a cancelled or expired appointment', function (): void {
    $ids = appointmentScenario();

    $otherMember = MemberModel::factory()->create(['business_id' => $ids['business']])->id;

    insertAppointment($ids);
    insertAppointment($ids, ['member_id' => $otherMember]);
    insertAppointment($ids, [
        'status' => 'cancelled',
        'cancelled_at' => '2026-12-01 09:00:00-06',
        'cancel_reason' => 'La clienta avisÃ³',
    ]);
    insertAppointment($ids, ['status' => 'expired']);

    expect(DB::table('appointments')->count())->toBe(4);
});

test('enforces the isolation policy of both appointment tables with the application role', function (): void {
    $ids = appointmentScenario();
    $second = appointmentScenario();
    // Creating the second scenario pinned its business, so the first one is pinned again before writing its rows.
    $this->scopeQueriesTo($ids['business']);

    insertAppointment($ids);
    $appointmentId = DB::table('appointments')->value('id');

    DB::table('appointment_perfumes')->insert([
        'business_id' => $ids['business'],
        'appointment_id' => $appointmentId,
        'perfume_id' => $ids['perfume'],
    ]);

    expect(appointmentWriteError(fn () => DB::table('appointments')->where('id', $appointmentId)->update(['business_id' => $second['business']]))?->getCode())
        ->toBe('42501');

    expect(appointmentWriteError(fn () => DB::table('appointment_perfumes')->where('appointment_id', $appointmentId)->update(['business_id' => $second['business']]))?->getCode())
        ->toBe('42501');

    // The rows of the other business are invisible to the pinned context.
    $this->scopeQueriesTo($second['business']);

    expect(DB::table('appointments')->count())->toBe(0);
});

test('keeps one row per perfume and appointment and cascades the ones of a deleted appointment', function (): void {
    $ids = appointmentScenario();

    insertAppointment($ids);
    $appointmentId = DB::table('appointments')->value('id');
    $secondPerfume = PerfumeModel::factory()->create(['business_id' => $ids['business']])->id;

    $first = ['business_id' => $ids['business'], 'appointment_id' => $appointmentId, 'perfume_id' => $ids['perfume']];

    DB::table('appointment_perfumes')->insert($first);
    DB::table('appointment_perfumes')->insert([
        'business_id' => $ids['business'],
        'appointment_id' => $appointmentId,
        'perfume_id' => $secondPerfume,
    ]);

    expect(DB::table('appointment_perfumes')->count())->toBe(2);

    expect(fn () => DB::transaction(fn () => DB::table('appointment_perfumes')->insert($first)))
        ->toThrow(QueryException::class);

    DB::table('appointments')->where('id', $appointmentId)->delete();

    expect(DB::table('appointment_perfumes')->count())->toBe(0);
});
