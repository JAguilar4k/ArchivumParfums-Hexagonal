<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Entities;

use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;
use Src\Payments\Payments\Domain\ValueObjects\ManualRecord;
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
    /** Minutes a pending charge waits for the provider's notice before the reconciliation asks the provider itself (#96). */
    public const int MINUTES_FOR_THE_NOTICE = 5;

    private function __construct(
        public readonly PaymentId $id,
        public readonly BusinessId $businessId,
        public readonly Payable $payable,
        public readonly PaymentPurpose $purpose,
        public readonly Colones $amount,
        public readonly string $gateway,
        public readonly ?PaymentMethod $method,
        public readonly ?string $exchangeRate,
        public private(set) PaymentStatus $status,
        public private(set) ?string $providerReference,
        public private(set) ?string $checkoutUrl,
        public private(set) ?string $checkoutReference,
        public private(set) ?string $captureReference,
        public private(set) string $chargedCurrency,
        public private(set) int $chargedAmount,
        public private(set) int $refundedAmount,
        public readonly ?ManualRecord $manual = null,
    ) {
        if ($amount->amount === 0 || ! in_array($gateway, ['onvo', 'paypal', 'manual'], true)) {
            throw new InvalidValueException('payments.invalid_payment');
        }
        // A gateway charges with an online method; a payment received on delivery has the owner's record instead.
        if (($gateway === 'manual') !== ($method === null) || ($gateway === 'manual') !== ($manual !== null)) {
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

    /**
     * Record the balance that the customer paid the owner on delivery, by SINPE or in cash (R29). No gateway takes
     * part, so the payment is confirmed as it is recorded, in colones, with the owner's record as its only reference.
     */
    public static function recordManually(PaymentId $id, BusinessId $businessId, Payable $payable, Colones $amount, ManualRecord $record): self
    {
        return new self($id, $businessId, $payable, PaymentPurpose::Balance, $amount, 'manual', null, null, PaymentStatus::Succeeded, null, null, null, null, 'CRC', $amount->amount, 0, $record);
    }

    /** Restore persisted state without repeating a provider operation. */
    public static function reconstitute(PaymentId $id, BusinessId $businessId, Payable $payable, PaymentPurpose $purpose, Colones $amount, string $gateway, ?PaymentMethod $method, ?string $exchangeRate, PaymentStatus $status, ?string $providerReference, ?string $checkoutUrl, ?string $checkoutReference, ?string $captureReference, string $chargedCurrency, int $chargedAmount, int $refundedAmount, ?ManualRecord $manual = null): self
    {
        return new self($id, $businessId, $payable, $purpose, $amount, $gateway, $method, $exchangeRate, $status, $providerReference, $checkoutUrl, $checkoutReference, $captureReference, $chargedCurrency, $chargedAmount, $refundedAmount, $manual);
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

    /** Whether a reference of the provider names this payment's charge, checkout or capture. */
    public function hasReference(string $reference): bool
    {
        return $reference !== '' && in_array($reference, [$this->providerReference, $this->checkoutReference, $this->captureReference], true);
    }

    /**
     * Whether a provider reports exactly what this payment charged, in the currency and unit the payment keeps. A report
     * of a failure may leave the amount out, as ONVO's notice of a failed charge does; its currency must still match.
     */
    public function matchesCharge(?string $currency, ?int $amount, bool $failure = false): bool
    {
        return $this->chargedAmount > 0 && $currency === $this->chargedCurrency
            && ($amount === $this->chargedAmount || ($failure && $amount === null));
    }

    /**
     * Start a refund of this payment (R40). Only a confirmed payment returns money, and its refunds never pass what it
     * paid and has not returned yet, counting the ones still pending (R30, R31). The caller holds the lock of the
     * payment's row, so two refunds of the same payment cannot both count on the same colones.
     *
     * @param  int  $pending  colones of the refunds of this payment that are still pending
     */
    public function requestRefund(string $refundId, int $amount, int $pending, ?string $orderLineId = null): Refund
    {
        if (! in_array($this->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true)) {
            throw new InvalidPaymentTransitionException('payments.not_refundable');
        }

        if ($amount <= 0 || $pending < 0 || $amount > $this->amount->amount - $this->refundedAmount - $pending) {
            throw new InvalidPaymentTransitionException('payments.invalid_refund');
        }

        return Refund::create($refundId, $this->businessId->value, $this->id->value, $amount, $orderLineId);
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
