<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function () {
    TwoBusinesses::create();
});

test('rejects a service with invalid duration or price', function () {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    expect(fn () => DB::transaction(fn () => DB::table('services')->insert([
        'business_id' => $businessId, 'name' => 'Test', 'duration_minutes' => 0, 'price' => 100,
    ])))->toThrow(QueryException::class, 'services_duration_minutes_check');

    expect(fn () => DB::transaction(fn () => DB::table('services')->insert([
        'business_id' => $businessId, 'name' => 'Test', 'duration_minutes' => 30, 'price' => -5,
    ])))->toThrow(QueryException::class, 'services_price_check');
});

test('rejects overlapping business hours but accepts contiguous ones', function () {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00',
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 1, 'opens_at' => '11:00', 'closes_at' => '13:00',
    ])))->toThrow(QueryException::class, 'business_hours_no_overlap_excl');

    DB::table('business_hours')->insert([
        'business_id' => $businessId, 'weekday' => 1, 'opens_at' => '12:00', 'closes_at' => '14:00',
    ]);

    expect(DB::table('business_hours')->where('business_id', $businessId)->count())->toBe(2);
});

test('rejects overlapping staff schedules for the same member and weekday', function () {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    $memberId = DB::table('members')->where('business_id', $businessId)->value('id');

    DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '14:00',
    ])))->toThrow(QueryException::class, 'staff_schedules_no_overlap_excl');

    DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '16:00',
    ]);

    expect(DB::table('staff_schedules')->where('business_id', $businessId)->count())->toBe(2);
});

test('composite foreign key prevents assigning member of another business to staff schedules and schedule blocks', function () {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $otherBusinessId = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    $this->scopeQueriesTo($otherBusinessId);
    $otherMemberId = DB::table('members')->where('business_id', $otherBusinessId)->value('id');

    $this->scopeQueriesTo($businessId);

    expect(fn () => DB::transaction(fn () => DB::table('staff_schedules')->insert([
        'business_id' => $businessId, 'member_id' => $otherMemberId, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => DB::table('schedule_blocks')->insert([
        'business_id' => $businessId, 'member_id' => $otherMemberId, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Test',
    ])))->toThrow(QueryException::class);
});

test('rejects schedule blocks with end before or equal to start', function () {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);

    expect(fn () => DB::transaction(fn () => DB::table('schedule_blocks')->insert([
        'business_id' => $businessId, 'starts_at' => now(), 'ends_at' => now()->subHour(), 'reason' => 'Invalid time',
    ])))->toThrow(QueryException::class, 'schedule_blocks_time_check');
});
