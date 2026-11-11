<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Src\Payments\Payments\Application\UseCases\StartPayment;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class);

// The purchase sent again answers the last attempt of a pack that no longer waits, so only StartPayment itself is left
// to refuse a new charge of a pack that was already paid, used up or expired.
it('starts no payment of a pack that no longer waits for it, so a pack paid, used up or expired is never charged again', function (array $pack) {
    $business = StaffedBusiness::create();
    $stored = StoredPacks::create($business, $pack);
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($business->business->id)));
    Http::fake();

    expect(fn () => app(StartPayment::class)->execute(new Payable('decant_pack', $stored['pack_id']), $business->customer->id))
        ->toThrow(InvalidPaymentTransitionException::class, 'payments.not_payable');

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseCount('payments', 0);
    Http::assertNothingSent();
})->with([
    'active' => [['status' => 'active', 'expires_at' => now()->addDays(180)]],
    'exhausted' => [['status' => 'exhausted', 'remaining_decants' => 0]],
    'expired' => [['status' => 'expired']],
]);
