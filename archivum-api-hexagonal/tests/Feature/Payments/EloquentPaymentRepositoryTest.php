<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class);

/** Read a stored payment and settle it in memory with what the provider answered, without saving it. */
function settledCopyOf(string $paymentId, PaymentStatus $status, string $capture): Payment
{
    $payment = app(EloquentPaymentRepository::class)->find(new PaymentId($paymentId)) ?? throw new LogicException('The payment was not stored.');
    $payment->attach(new ChargeResult('session-'.$paymentId, $status, 'CRC', 7500, captureReference: $capture));
    $payment->apply($status);

    return $payment;
}

it('R38: takes a payment out of pending only once, even with two copies read while it was pending', function (): void {
    $business = StaffedBusiness::create();
    $stored = StoredPayments::create($business, StoredOrders::create($business)['order']);
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($business->business->id)));
    app(BusinessTransaction::class)->scope($business->business->id);
    $succeeded = settledCopyOf($stored->id, PaymentStatus::Succeeded, 'intent-first');
    $failed = settledCopyOf($stored->id, PaymentStatus::Failed, 'intent-second');

    expect(app(EloquentPaymentRepository::class)->saveIfPending($succeeded))->toBeTrue()
        ->and(app(EloquentPaymentRepository::class)->saveIfPending($failed))->toBeFalse();

    assertDatabaseHas('payments', ['id' => $stored->id, 'status' => 'succeeded', 'capture_reference' => 'intent-first']);
});

it('stores a payment of a decant pack on its own column, finds its pending attempt and rebuilds what it pays', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $packId = StoredPacks::create($business)['pack_id'];
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($business->business->id)));
    app(BusinessTransaction::class)->scope($business->business->id);
    $repository = app(EloquentPaymentRepository::class);
    $pack = new Payable('decant_pack', $packId);
    $payment = Payment::create($repository->nextId(), new BusinessId($business->business->id), $pack, PaymentPurpose::Full, new Colones(40000), 'onvo', PaymentMethod::Card);

    $repository->save($payment);

    assertDatabaseHas('payments', ['id' => $payment->id->value, 'decant_pack_id' => $packId, 'order_id' => null, 'purpose' => 'full', 'amount' => 40000]);
    expect($repository->find($payment->id)?->payable)->toEqual($pack)
        ->and($repository->pending($pack, PaymentPurpose::Full)?->id)->toEqual($payment->id)
        // An order with the same identifier as the pack would not be the pack: each kind is read from its own column.
        ->and($repository->pending(new Payable('order', $packId), PaymentPurpose::Full))->toBeNull()
        ->and($repository->pending(new Payable('decant_pack', $order->id), PaymentPurpose::Full))->toBeNull();
});

it('answers the last attempt to pay a pack or an order, whatever its status', function (): void {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    $packId = StoredPacks::create($business)['pack_id'];
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($business->business->id)));
    app(BusinessTransaction::class)->scope($business->business->id);
    $repository = app(EloquentPaymentRepository::class);
    $pack = new Payable('decant_pack', $packId);
    $first = Payment::create($repository->nextId(), new BusinessId($business->business->id), $pack, PaymentPurpose::Full, new Colones(40000), 'onvo', PaymentMethod::Card);
    $first->apply(PaymentStatus::Failed);
    $repository->save($first);
    $second = Payment::create($repository->nextId(), new BusinessId($business->business->id), $pack, PaymentPurpose::Full, new Colones(40000), 'onvo', PaymentMethod::Card);
    $repository->save($second);
    $orderPayment = StoredPayments::create($business, $order);

    expect($repository->last($pack)?->id)->toEqual($second->id)
        ->and($repository->last(new Payable('order', $order->id))?->id->value)->toBe($orderPayment->id)
        ->and($repository->last(new Payable('decant_pack', (string) Str::uuid7())))->toBeNull();
});
