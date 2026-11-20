<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('rejects unsupported notice types in PostgreSQL and accepts both confirmation types', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $attempt = [
        'business_id' => $business->id,
        'reference_id' => (string) Str::uuid7(),
    ];

    expect(fn () => DB::transaction(fn (): bool => DB::table('customer_notice_attempts')->insert([
        ...$attempt,
        'notice_type' => 'unsupported_notice',
    ])))->toThrow(QueryException::class, 'customer_notice_attempts_notice_type_check');

    DB::table('customer_notice_attempts')->insert([
        [...$attempt, 'notice_type' => 'order_placed'],
        [...$attempt, 'notice_type' => 'appointment_booked'],
    ]);

    expect(DB::table('customer_notice_attempts')->orderBy('notice_type')->pluck('notice_type')->all())
        ->toBe(['appointment_booked', 'order_placed']);
});
