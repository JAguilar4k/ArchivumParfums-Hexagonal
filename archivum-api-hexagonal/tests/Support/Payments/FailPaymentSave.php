<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use RuntimeException;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;

/** Keep the real PostgreSQL repository while injecting a failure at a durable boundary. */
final readonly class FailPaymentSave implements PaymentRepository
{
    public function __construct(private PaymentRepository $repository, private bool $afterReference = false) {}

    public function nextId(): PaymentId
    {
        return $this->repository->nextId();
    }

    public function find(PaymentId $id, bool $forUpdate = false): ?Payment
    {
        return $this->repository->find($id, $forUpdate);
    }

    public function pending(Payable $payable, PaymentPurpose $purpose): ?Payment
    {
        return $this->repository->pending($payable, $purpose);
    }

    public function save(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Succeeded || ($this->afterReference && $payment->providerReference !== null)) {
            throw new RuntimeException('Injected payment persistence failure.');
        }

        $this->repository->save($payment);
    }
}
