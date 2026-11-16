<?php

declare(strict_types=1);

use Src\Payments\Payments\Application\Contracts\PendingPayments;
use Src\Payments\Payments\Application\DTOs\PaymentReference;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * The identifiers of the payments that the reconciliation of #96 reads at that moment.
 *
 * @return list<string>
 */
function paymentsDueAt(string $at): array
{
    return array_map(fn (PaymentReference $payment): string => $payment->paymentId, app(PendingPayments::class)->dueAt(new DateTimeImmutable($at), 50));
}

it('R39: reads for 24 hours the expired payments of expired orders and packs, and never those of an order or a pack that is open or paid', function (): void {
    travelTo(new DateTimeImmutable('2026-11-10T15:00:00-06:00'));
    $business = StaffedBusiness::create();
    // A pack that still waits for its payment: one attempt is pending and ONVO itself expired the checkout of another.
    $waitingPack = StoredPacks::create($business)['pack_id'];
    $pending = StoredPayments::forPack($business, $waitingPack);
    StoredPayments::forPack($business, $waitingPack, 'expired');
    // A pack that an earlier attempt could not pay and a later one activated, and an order that still waits.
    StoredPayments::forPack($business, StoredPacks::create($business, ['status' => 'active', 'expires_at' => now()->addDays(180)])['pack_id'], 'expired');
    StoredPayments::create($business, StoredOrders::create($business)['order'], 'expired');
    // A pack and an order whose expiry closed their payment just now (#158, #159).
    $lateForPack = StoredPayments::forPack($business, StoredPacks::create($business, ['status' => 'expired', 'expires_at' => now()])['pack_id'], 'expired');
    $lateForOrder = StoredPayments::create($business, StoredOrders::create($business, 'expired')['order'], 'expired');

    // Each one first waits five minutes for its notice, as a pending payment does.
    expect(paymentsDueAt('2026-11-10T15:04:59-06:00'))->toBe([])
        ->and(paymentsDueAt('2026-11-10T15:05:00-06:00'))->toEqualCanonicalizing([$pending->id, $lateForPack->id, $lateForOrder->id])
        ->and(paymentsDueAt('2026-11-11T14:59:59-06:00'))->toEqualCanonicalizing([$pending->id, $lateForPack->id, $lateForOrder->id])
        // A day after the expiry closed them, only the pending payment is still read.
        ->and(paymentsDueAt('2026-11-11T15:00:00-06:00'))->toBe([$pending->id]);
});
