<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Entities;

use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final class Payment
{
    private function __construct(
        public readonly PaymentId $id,
        public readonly BusinessId $businessId,
        public readonly Payable $payable,
        public readonly PaymentPurpose $purpose,
        public readonly Colones $amount,
        public readonly string $gateway,
        public readonly PaymentMethod $method,
        public readonly ?string $exchangeRate,
        public private(set) PaymentStatus $status,
        public private(set) ?string $providerReference,
        public private(set) ?string $checkoutUrl,
        public private(set) ?string $checkoutReference,
        public private(set) ?string $captureReference,
        public private(set) string $chargedCurrency,
        public private(set) int $chargedAmount,
        public private(set) int $refundedAmount,
    ) {
        if ($amount->amount === 0 || ! in_array($gateway, ['onvo', 'paypal', 'manual'], true)) {
            throw new InvalidValueException('payments.invalid_payment');
        }
        if ($gateway === 'paypal') {
            new ExchangeRate($exchangeRate ?? '');
        }
        if (! in_array($chargedCurrency, ['CRC', 'USD'], true) || $chargedAmount < 0 || $refundedAmount < 0 || $refundedAmount > $amount->amount) {
            throw new InvalidValueException('payments.invalid_payment');
        }
    }

    /** A newly requested charge cannot confirm the resource it pays. */
    public static function create(PaymentId $id, BusinessId $businessId, Payable $payable, PaymentPurpose $purpose, Colones $amount, string $gateway, PaymentMethod $method, ?string $exchangeRate = null): self
    {
        return new self($id, $businessId, $payable, $purpose, $amount, $gateway, $method, $exchangeRate, PaymentStatus::Pending, null, null, null, null, $gateway === 'paypal' ? 'USD' : 'CRC', 0, 0);
    }

    /** Restore persisted state without repeating a provider operation. */
    public static function reconstitute(PaymentId $id, BusinessId $businessId, Payable $payable, PaymentPurpose $purpose, Colones $amount, string $gateway, PaymentMethod $method, ?string $exchangeRate, PaymentStatus $status, ?string $providerReference, ?string $checkoutUrl, ?string $checkoutReference, ?string $captureReference, string $chargedCurrency, int $chargedAmount, int $refundedAmount): self
    {
        return new self($id, $businessId, $payable, $purpose, $amount, $gateway, $method, $exchangeRate, $status, $providerReference, $checkoutUrl, $checkoutReference, $captureReference, $chargedCurrency, $chargedAmount, $refundedAmount);
    }

    /** Persist the provider identity before any subsequent confirmation query. */
    public function attach(ChargeResult $result): void
    {
        if ($this->status !== PaymentStatus::Pending) {
            throw new InvalidPaymentTransitionException('payments.invalid_transition');
        }

        $this->providerReference = $result->providerReference;
        $this->checkoutReference = $result->checkoutReference ?? $this->checkoutReference;
        $this->checkoutUrl = $result->checkoutUrl ?? $this->checkoutUrl;
        $this->captureReference = $result->captureReference ?? $this->captureReference;
        $this->chargedCurrency = $result->currency;
        $this->chargedAmount = $result->chargedAmount;
    }

    /** Apply only an authenticated server query, never a browser return value. */
    public function apply(PaymentStatus $status): void
    {
        if ($status === $this->status) {
            return;
        }

        if ($this->status !== PaymentStatus::Pending || ! in_array($status, [PaymentStatus::Succeeded, PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            throw new InvalidPaymentTransitionException('payments.invalid_transition');
        }

        $this->status = $status;
    }

    /** Record a confirmed refund without allowing a total greater than the original payment. */
    public function refund(int $amount): void
    {
        if (! in_array($this->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true) || $amount <= 0 || $amount > $this->amount->amount - $this->refundedAmount) {
            throw new InvalidPaymentTransitionException('payments.invalid_refund');
        }

        $this->refundedAmount += $amount;
        $this->status = $this->refundedAmount === $this->amount->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
    }
}
