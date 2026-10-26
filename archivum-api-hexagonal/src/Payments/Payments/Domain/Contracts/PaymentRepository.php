<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;

interface PaymentRepository
{
    public function nextId(): PaymentId;

    public function find(PaymentId $id, bool $forUpdate = false): ?Payment;

    public function pending(Payable $payable, PaymentPurpose $purpose): ?Payment;

    public function save(Payment $payment): void;
}
