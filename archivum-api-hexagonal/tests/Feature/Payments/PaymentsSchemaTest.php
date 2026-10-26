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
    $event = ['gateway' => 'onvo', 'event_id' => 'fictitious-event-1', 'event_type' => 'payment-intent.succeeded', 'payload' => '{}'];
    DB::table('webhook_events')->insert(['id' => (string) Str::uuid7(), ...$event]);
    DB::table('webhook_events')->insert(['id' => (string) Str::uuid7(), ...$event, 'gateway' => 'paypal']);

    expect(fn () => DB::transaction(fn (): bool => DB::table('webhook_events')->insert(['id' => (string) Str::uuid7(), ...$event])))->toThrow(QueryException::class, 'webhook_events_gateway_event_id_unique');
    expect(DB::table('webhook_events')->count())->toBe(2);
});
