<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Payments\Payments\Application\DTOs\PayablePack;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Infrastructure\Decants\DecantsPayablePackReader;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\freezeSecond;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** The packs of the Decants module as payments read them, in an operation of the business given. */
function payablePacksOf(string $businessId): DecantsPayablePackReader
{
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));

    return app(DecantsPayablePackReader::class);
}

/**
 * The queries that read decant packs while the callback runs.
 *
 * @return list<string>
 */
function packQueriesOf(Closure $callback): array
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $callback();
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    return array_values(array_filter($queries, fn (string $query): bool => str_contains($query, 'from "decant_packs"')));
}

it('locks the pack the customer bought to start its payment, with the price of its offer and the gateway of the store', function () {
    $business = StaffedBusiness::create();
    $stored = StoredPacks::create($business);
    $this->scopeQueriesTo($business->business->id);
    $reader = payablePacksOf($business->business->id);
    $payable = null;

    $queries = packQueriesOf(function () use ($reader, $stored, $business, &$payable): void {
        $payable = $reader->findForPayment($stored['pack_id'], $business->customer->id);
    });

    expect($payable)->toEqual(new PayablePack($stored['pack_id'], $business->business->id, 'pending_payment', 40000, 'onvo', null))
        ->and($queries)->toHaveCount(1)
        ->and($queries[0])->toEndWith('for update');
});

it('reads the exchange rate of the store only to start a PayPal payment, not to settle one', function () {
    $business = StaffedBusiness::create();
    $business->business->update(['settings' => ['default_gateway' => 'paypal', 'usd_exchange_rate' => 500]]);
    $stored = StoredPacks::create($business);
    $this->scopeQueriesTo($business->business->id);
    $reader = payablePacksOf($business->business->id);

    expect($reader->findForPayment($stored['pack_id'], $business->customer->id))
        ->toEqual(new PayablePack($stored['pack_id'], $business->business->id, 'pending_payment', 40000, 'paypal', '500.000000'))
        ->and($reader->findForUpdate($stored['pack_id'])->exchangeRate)->toBeNull();
});

it('finds no pack to pay for an account that did not buy it, exactly as for a pack that does not exist', function (Closure $accountOf) {
    $business = StaffedBusiness::create();
    $stored = StoredPacks::create($business);
    $this->scopeQueriesTo($business->business->id);
    $reader = payablePacksOf($business->business->id);

    expect(fn () => $reader->findForPayment($stored['pack_id'], $accountOf($business)))->toThrow(PaymentNotFoundException::class, 'payments.not_found')
        ->and(fn () => $reader->findForPayment((string) Str::uuid7(), $business->customer->id))->toThrow(PaymentNotFoundException::class, 'payments.not_found')
        ->and(fn () => $reader->findForUpdate((string) Str::uuid7()))->toThrow(PaymentNotFoundException::class, 'payments.not_found');
})->with([
    'the owner of the store' => [fn (StaffedBusiness $business): string => $business->owner->id],
    'another customer of the store' => [fn (StaffedBusiness $business): string => (string) CustomerModel::factory()->create(['business_id' => $business->business->id])->user_id],
]);

it('starts no payment once the deadline of the pack passed, but still locks it so its settlement keeps the charge', function () {
    $business = StaffedBusiness::create();
    $stored = StoredPacks::create($business, ['expires_at' => now()->subSecond()]);
    $this->scopeQueriesTo($business->business->id);
    $reader = payablePacksOf($business->business->id);

    expect(fn () => $reader->findForPayment($stored['pack_id'], $business->customer->id))->toThrow(InvalidPaymentTransitionException::class, 'payments.not_payable')
        ->and($reader->findForUpdate($stored['pack_id'])->status)->toBe('pending_payment');
});

it('R33: activates the pack whose payment was confirmed, valid for the days of its offer from this moment', function () {
    // The confirmation is recorded to the second, like the instant the use case receives.
    freezeSecond();
    $business = StaffedBusiness::create();
    $stored = StoredPacks::create($business);
    $this->scopeQueriesTo($business->business->id);

    payablePacksOf($business->business->id)->applyPayment($stored['pack_id']);

    $row = DB::table('decant_packs')->where('id', $stored['pack_id'])->first(['status', 'remaining_decants', 'expires_at']);
    expect($row?->status)->toBe('active')
        ->and($row?->remaining_decants)->toBe(5)
        ->and(CarbonImmutable::parse((string) $row?->expires_at)->equalTo(now()->addDays(180)))->toBeTrue();
});

it('finds no pack of another business, whatever the database is scoped to', function () {
    $mine = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $theirs = StoredPacks::create($other);
    // The database still reaches the other business: only the business of the operation hides its pack.
    $this->scopeQueriesTo($other->business->id);
    $reader = payablePacksOf($mine->business->id);

    expect(fn () => $reader->findForPayment($theirs['pack_id'], $other->customer->id))->toThrow(PaymentNotFoundException::class, 'payments.not_found')
        ->and(fn () => $reader->findForUpdate($theirs['pack_id']))->toThrow(PaymentNotFoundException::class, 'payments.not_found');
});
