<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

pest()->use(RefreshDatabaseAsOwner::class);

it('rejects invalid payment values with the named PostgreSQL checks', function (string $field, mixed $value, string $constraint): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    app(BusinessTransaction::class)->scope($business->business->id);

    expect(fn () => DB::transaction(fn (): bool => DB::table('payments')->insert([
        'id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'order_id' => $order->id,
        'purpose' => 'deposit', 'amount' => 7500, 'gateway' => 'onvo', 'status' => 'pending', $field => $value,
    ])))->toThrow(QueryException::class, $constraint);
})->with([
    ['amount', -1, 'payments_amount_check'],
    ['amount', 0, 'payments_amount_check'],
    ['gateway', 'other', 'payments_gateway_check'],
    ['purpose', 'tip', 'payments_purpose_check'],
    ['status', 'approved', 'payments_status_check'],
    ['method', 'cash', 'payments_method_check'],
    ['charged_currency', 'EUR', 'payments_charged_currency_check'],
    ['charged_amount', -1, 'payments_charged_amount_check'],
    ['refunded_amount', 7501, 'payments_refunded_amount_check'],
    ['gateway', 'manual', 'payments_manual_reference_check'],
    ['charged_currency', 'USD', 'payments_exchange_rate_check'],
]);

it('prevents a payment from referencing another tenant order', function (): void {
    $first = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $order = StoredOrders::create($other)['order'];
    app(BusinessTransaction::class)->scope($first->business->id);

    expect(fn () => DB::transaction(fn (): bool => DB::table('payments')->insert([
        'id' => (string) Str::uuid7(), 'business_id' => $first->business->id, 'order_id' => $order->id,
        'purpose' => 'deposit', 'amount' => 7500, 'gateway' => 'onvo', 'status' => 'pending',
    ])))->toThrow(QueryException::class, 'payments_order_id_business_id_foreign');
});

it('prevents two pending attempts of the same order and purpose', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    StoredPayments::create($business, $order);
    app(BusinessTransaction::class)->scope($business->business->id);

    expect(fn () => DB::transaction(fn (): bool => DB::table('payments')->insert([
        'id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'order_id' => $order->id,
        'purpose' => 'deposit', 'amount' => 7500, 'gateway' => 'onvo', 'status' => 'pending',
    ])))->toThrow(QueryException::class, 'payments_one_pending_purpose');
});

it('rejects invalid refund values with the named database checks', function (string $field, mixed $value, string $constraint): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $payment = StoredPayments::create($business, $order, 'succeeded');
    app(BusinessTransaction::class)->scope($business->business->id);

    expect(fn () => DB::transaction(fn (): bool => DB::table('refunds')->insert([
        'id' => (string) Str::uuid7(), 'business_id' => $business->business->id, 'payment_id' => $payment->id,
        'amount' => 7500, 'status' => 'pending', $field => $value,
    ])))->toThrow(QueryException::class, $constraint);
})->with([['amount', 0, 'refunds_amount_check'], ['amount', -1, 'refunds_amount_check'], ['status', 'approved', 'refunds_status_check']]);

it('prevents a refund from referencing another tenant payment', function (): void {
    $first = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $order = StoredOrders::create($other)['order'];
    $payment = StoredPayments::create($other, $order, 'succeeded');
    app(BusinessTransaction::class)->scope($first->business->id);

    expect(fn () => DB::transaction(fn (): bool => DB::table('refunds')->insert([
        'id' => (string) Str::uuid7(), 'business_id' => $first->business->id,
        'payment_id' => $payment->id, 'amount' => 7500, 'status' => 'pending',
    ])))->toThrow(QueryException::class, 'refunds_payment_id_business_id_foreign');
});

it('enforces read and write tenant barriers using the non-owner application role', function (string $table): void {
    $first = StaffedBusiness::create();
    $order = StoredOrders::create($first)['order'];
    $payment = StoredPayments::create($first, $order, 'succeeded');
    $refundId = (string) Str::uuid7();
    app(BusinessTransaction::class)->scope($first->business->id);
    DB::table('refunds')->insert(['id' => $refundId, 'business_id' => $first->business->id, 'payment_id' => $payment->id, 'amount' => 7500]);
    $other = StaffedBusiness::create();
    $resourceId = $table === 'payments' ? $payment->id : $refundId;
    app(BusinessTransaction::class)->scope($first->business->id);

    expect(DB::scalar('select current_user'))->toBe(config('database.connections.pgsql.username'));
    expect(DB::scalar('select rolbypassrls from pg_roles where rolname = current_user'))->toBeFalse();
    expect(DB::scalar('select relrowsecurity and relforcerowsecurity from pg_class where oid = ?::regclass', [$table]))->toBeTrue();
    expect(DB::table($table)->where('id', $resourceId)->exists())->toBeTrue();
    app(BusinessTransaction::class)->scope($other->business->id);
    expect(DB::table($table)->where('id', $resourceId)->exists())->toBeFalse();
    expect(DB::table($table)->where('id', $resourceId)->update(['amount' => 1]))->toBe(0);
    app(BusinessTransaction::class)->scope(null);
    expect(DB::table($table)->count())->toBe(0);
    app(BusinessTransaction::class)->scope($first->business->id);
    expect(fn () => DB::transaction(fn (): int => DB::table($table)->where('id', $resourceId)->update(['business_id' => $other->business->id])))->toThrow(QueryException::class, 'row-level security');
})->with(['payments', 'refunds']);

it('records each webhook event identity only once per provider', function (): void {
    $event = ['gateway' => 'onvo', 'event_id' => 'fictitious-event-1', 'event_type' => 'payment-intent.succeeded', 'payload' => '{}', 'status' => 'failed', 'error' => 'payments.not_found'];
    DB::table('webhook_events')->insert(['id' => (string) Str::uuid7(), ...$event]);
    DB::table('webhook_events')->insert(['id' => (string) Str::uuid7(), ...$event, 'gateway' => 'paypal']);

    expect(fn () => DB::transaction(fn (): bool => DB::table('webhook_events')->insert(['id' => (string) Str::uuid7(), ...$event])))->toThrow(QueryException::class, 'webhook_events_gateway_event_id_unique');
    expect(DB::table('webhook_events')->count())->toBe(2);
});

it('keeps the outcome of each webhook event with the named PostgreSQL checks', function (array $outcome, string $constraint): void {
    expect(fn () => DB::transaction(fn (): bool => DB::table('webhook_events')->insert([
        'id' => (string) Str::uuid7(), 'gateway' => 'onvo', 'event_id' => 'fictitious-event-1', 'event_type' => 'checkout-session.succeeded', 'payload' => '{}', ...$outcome,
    ])))->toThrow(QueryException::class, $constraint);
})->with([
    'an unknown status' => [['status' => 'ignored'], 'webhook_events_status_check'],
    'a failure without error' => [['status' => 'failed'], 'webhook_events_error_check'],
    'a processed event with an error' => [['status' => 'processed', 'processed_at' => now(), 'error' => 'payments.not_found'], 'webhook_events_error_check'],
    'a processed event without its moment' => [['status' => 'processed'], 'webhook_events_processed_at_check'],
    'a failure with a moment of processing' => [['status' => 'failed', 'error' => 'payments.not_found', 'processed_at' => now()], 'webhook_events_processed_at_check'],
    'a pending event with an error' => [['status' => 'pending', 'error' => 'payments.not_found'], 'webhook_events_error_check'],
    'a pending event with a moment of processing' => [['status' => 'pending', 'processed_at' => now()], 'webhook_events_processed_at_check'],
]);

it('keeps a new webhook event pending until it is processed', function (): void {
    $id = (string) Str::uuid7();
    DB::table('webhook_events')->insert(['id' => $id, 'gateway' => 'onvo', 'event_id' => 'fictitious-event-1', 'event_type' => 'checkout-session.succeeded', 'payload' => '{}']);

    expect(DB::table('webhook_events')->where('id', $id)->first(['status', 'error', 'processed_at']))
        ->toEqual((object) ['status' => 'pending', 'error' => null, 'processed_at' => null]);
});

it('finds the business of any reference of a payment only through fn_payment_business', function (): void {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order']);
    app(BusinessTransaction::class)->scope(null);

    expect(DB::table('payments')->count())->toBe(0);
    foreach ([$payment->provider_reference, $payment->checkout_reference, $payment->capture_reference] as $reference) {
        expect(DB::scalar('select fn_payment_business(?, ?)', ['onvo', $reference]))->toBe($business->business->id);
    }
    expect(DB::scalar('select fn_payment_business(?, ?)', ['paypal', $payment->provider_reference]))->toBeNull()
        ->and(DB::scalar('select fn_payment_business(?, ?)', ['onvo', 'session-unknown']))->toBeNull();
});

it('runs fn_payment_business as the schema owner, with an empty search_path, and lets only the application role call it', function (): void {
    $definition = DB::selectOne(
        "select prosecdef, proconfig, pg_get_userbyid(proowner) as owner,
                array(select coalesce(nullif(grantee::regrole::text, '-'), 'PUBLIC')
                        from aclexplode(proacl) where privilege_type = 'EXECUTE' order by 1) as callers
           from pg_proc where oid = 'fn_payment_business(text, text)'::regprocedure",
    );
    $owner = config('database.connections.pgsql_admin.username');
    $callers = [$owner, config('database.connections.pgsql.username')];
    sort($callers);

    expect($definition->prosecdef)->toBeTrue()
        ->and($definition->proconfig)->toBe('{"search_path=\"\""}')
        ->and($definition->owner)->toBe($owner)
        ->and($definition->callers)->toBe('{'.implode(',', $callers).'}');
});
