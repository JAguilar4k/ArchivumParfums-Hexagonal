<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * A business with an order of one decant and a pack of its customer that waits for its payment.
 *
 * @return array{business_id: string, customer_id: string, plan_id: string, pack_id: string, line_id: string}
 */
function businessWithAPack(): array
{
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business, withBottle: false, withDecant: true)['order'];
    $stored = StoredPacks::create($business);
    $lineId = app(BusinessTransaction::class)->run($business->business->id, fn (): string => (string) OrderLineModel::query()
        ->where('order_id', $order->id)->where('kind', 'decant')->value('id'));

    return ['business_id' => $business->business->id, ...$stored, 'line_id' => $lineId];
}

/**
 * A redemption of the given pack for the given line of an order, in the business of the row.
 *
 * @return array<string, mixed>
 */
function packRedemptionRow(string $businessId, string $packId, string $lineId): array
{
    return ['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'decant_pack_id' => $packId, 'order_line_id' => $lineId, 'created_at' => now(), 'updated_at' => now()];
}

it('rejects an offer whose name, decants, ml, price, validity or cap is not positive, with the named checks', function (string $column, mixed $value, string $constraint) {
    $stored = businessWithAPack();
    app(BusinessTransaction::class)->scope($stored['business_id']);

    expect(fn () => DB::transaction(fn (): int => DB::table('decant_pack_plans')->where('id', $stored['plan_id'])->update([$column => $value])))
        ->toThrow(QueryException::class, $constraint);
})->with([
    ['name', '   ', 'decant_pack_plans_name_check'],
    ['decants_count', 0, 'decant_pack_plans_decants_count_check'],
    ['decants_count', -5, 'decant_pack_plans_decants_count_check'],
    ['ml_per_decant', 0, 'decant_pack_plans_ml_per_decant_check'],
    ['price', 0, 'decant_pack_plans_price_check'],
    ['price', -40000, 'decant_pack_plans_price_check'],
    ['validity_days', 0, 'decant_pack_plans_validity_days_check'],
    ['max_price_per_ml', 0, 'decant_pack_plans_max_price_per_ml_check'],
]);

it('R34: keeps the decants left of a pack between 0 and what it bought, and agreeing with its status', function (array $changes, string $constraint) {
    $stored = businessWithAPack();
    app(BusinessTransaction::class)->scope($stored['business_id']);

    expect(fn () => DB::transaction(fn (): int => DB::table('decant_packs')->where('id', $stored['pack_id'])->update($changes)))
        ->toThrow(QueryException::class, $constraint);
})->with([
    'a negative balance' => [['status' => 'active', 'remaining_decants' => -1], 'decant_packs_remaining_decants_check'],
    'more decants left than bought' => [['status' => 'expired', 'remaining_decants' => 6], 'decant_packs_remaining_decants_check'],
    'no decants bought' => [['total_decants' => 0, 'remaining_decants' => 0], 'decant_packs_total_decants_check'],
    'decants without ml' => [['ml_per_decant' => 0], 'decant_packs_ml_per_decant_check'],
    'a cap of nothing' => [['max_price_per_ml' => 0], 'decant_packs_max_price_per_ml_check'],
    'an unknown status' => [['status' => 'paid'], 'decant_packs_status_check'],
    'unpaid with a decant redeemed' => [['remaining_decants' => 4], 'decant_packs_status_check'],
    'active without decants' => [['status' => 'active', 'remaining_decants' => 0], 'decant_packs_status_check'],
    'exhausted with decants left' => [['status' => 'exhausted', 'remaining_decants' => 1], 'decant_packs_status_check'],
]);

it('accepts every status of section 6 with the decants left it allows', function (string $status, int $remaining) {
    $stored = businessWithAPack();
    app(BusinessTransaction::class)->scope($stored['business_id']);

    expect(DB::table('decant_packs')->where('id', $stored['pack_id'])->update(['status' => $status, 'remaining_decants' => $remaining]))->toBe(1);
})->with([['active', 5], ['active', 1], ['exhausted', 0], ['expired', 3], ['expired', 0], ['pending_payment', 5]]);

it('R56: keeps the customer and the offer of a pack, its redemptions and the pack of a line in the business of their row', function (string $table, Closure $row, string $constraint) {
    $first = businessWithAPack();
    $other = businessWithAPack();
    app(BusinessTransaction::class)->scope($first['business_id']);

    expect(fn () => DB::transaction(fn () => $row($first, $other)))->toThrow(QueryException::class, $constraint);
})->with([
    'a pack of a customer of another business' => ['decant_packs', fn (array $first, array $other): bool => DB::table('decant_packs')->insert([
        'business_id' => $first['business_id'], 'customer_id' => $other['customer_id'], 'decant_pack_plan_id' => $first['plan_id'],
        'total_decants' => 5, 'remaining_decants' => 5, 'ml_per_decant' => 5, 'expires_at' => now(),
    ]), 'decant_packs_customer_id_business_id_foreign'],
    'a pack of an offer of another business' => ['decant_packs', fn (array $first, array $other): bool => DB::table('decant_packs')->insert([
        'business_id' => $first['business_id'], 'customer_id' => $first['customer_id'], 'decant_pack_plan_id' => $other['plan_id'],
        'total_decants' => 5, 'remaining_decants' => 5, 'ml_per_decant' => 5, 'expires_at' => now(),
    ]), 'decant_packs_decant_pack_plan_id_business_id_foreign'],
    'a redemption of a pack of another business' => ['decant_pack_redemptions', fn (array $first, array $other): bool => DB::table('decant_pack_redemptions')
        ->insert(packRedemptionRow($first['business_id'], $other['pack_id'], $first['line_id'])), 'decant_pack_redemptions_decant_pack_id_business_id_foreign'],
    'a redemption of a line of another business' => ['decant_pack_redemptions', fn (array $first, array $other): bool => DB::table('decant_pack_redemptions')
        ->insert(packRedemptionRow($first['business_id'], $first['pack_id'], $other['line_id'])), 'decant_pack_redemptions_order_line_id_business_id_foreign'],
    'a line paid with a pack of another business' => ['order_lines', fn (array $first, array $other): int => DB::table('order_lines')
        ->where('id', $first['line_id'])->update(['decant_pack_id' => $other['pack_id']]), 'order_lines_decant_pack_id_business_id_foreign'],
]);

it('R34: redeems one decant for each line of an order, and only once', function () {
    $stored = businessWithAPack();
    app(BusinessTransaction::class)->scope($stored['business_id']);

    DB::table('decant_pack_redemptions')->insert(packRedemptionRow($stored['business_id'], $stored['pack_id'], $stored['line_id']));
    DB::table('order_lines')->where('id', $stored['line_id'])->update(['decant_pack_id' => $stored['pack_id']]);

    expect(fn () => DB::transaction(fn (): bool => DB::table('decant_pack_redemptions')->insert(packRedemptionRow($stored['business_id'], $stored['pack_id'], $stored['line_id']))))
        ->toThrow(QueryException::class, 'decant_pack_redemptions_business_id_order_line_id_unique')
        ->and(DB::table('decant_pack_redemptions')->count())->toBe(1)
        ->and(DB::table('order_lines')->where('id', $stored['line_id'])->value('decant_pack_id'))->toBe($stored['pack_id']);
});

it('R55: forces row level security on the offers, the packs and their redemptions, with the policy of the business', function () {
    $first = businessWithAPack();
    $other = businessWithAPack();
    foreach ([$first, $other] as $stored) {
        app(BusinessTransaction::class)->run($stored['business_id'], fn (): bool => DB::table('decant_pack_redemptions')
            ->insert(packRedemptionRow($stored['business_id'], $stored['pack_id'], $stored['line_id'])));
    }

    foreach (['decant_pack_plans', 'decant_packs', 'decant_pack_redemptions'] as $table) {
        $security = DB::selectOne('select relrowsecurity, relforcerowsecurity from pg_class where oid = ?::regclass', [$table]);

        app(BusinessTransaction::class)->scope(null);
        $unscoped = DB::table($table)->count();
        app(BusinessTransaction::class)->scope($first['business_id']);

        expect($security->relrowsecurity)->toBeTrue()
            ->and($security->relforcerowsecurity)->toBeTrue()
            ->and($unscoped)->toBe(0, "{$table} shows rows without a business.")
            ->and(DB::table($table)->pluck('business_id')->all())->toBe([$first['business_id']], "{$table} shows rows of another business.");
    }

    expect(fn () => DB::transaction(fn (): bool => DB::table('decant_pack_plans')->insert([
        'business_id' => $other['business_id'], 'name' => 'Ajena', 'decants_count' => 5, 'ml_per_decant' => 5, 'price' => 40000, 'validity_days' => 180,
    ])))->toThrow(QueryException::class, 'row-level security policy')
        ->and(DB::table('decant_packs')->where('id', $other['pack_id'])->update(['remaining_decants' => 0, 'status' => 'exhausted']))->toBe(0);
});
