<?php

declare(strict_types=1);

use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
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
