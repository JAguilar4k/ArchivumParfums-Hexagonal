<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Payments\Payments\Domain\ValueObjects\RefundResult;

interface PaymentGateway
{
    /** @return list<PaymentMethod> */
    public function supportedMethods(): array;

    /** Validate the provider-specific representation without making an HTTP request. */
    public function validateCharge(ChargeRequest $request): void;

    public function create(ChargeRequest $request): ChargeResult;

    public function status(ChargeReference $reference): ChargeResult;

    /**
     * The amount of a refund in the unit the provider charged the payment, without an HTTP request: whole colones with
     * ONVO, cents of a dollar with PayPal at the exchange rate of the original payment. The refunds of a payment convert
     * their running total, so together they never pass what the provider charged and match it when they return the
     * whole payment (R37).
     *
     * @param  int  $refundedBefore  colones of the earlier refunds of the payment that left for the provider and did not fail
     */
    public function refundAmount(int $amount, int $refundedBefore, ?string $exchangeRate): int;

    /**
     * Ask the provider to refund a charge and answer its reference and state; a refusal is a failed refund without a
     * reference. With the provider's reference of a refund it already accepted, only ask for its current state.
     */
    public function refund(RefundRequest $request): RefundResult;

    /**
     * Whether a refund request that got no answer can be sent again without returning the money twice, because the
     * provider recognizes the same request by a key Archivum sends with it and answers the refund it already created.
     */
    public function resendsRefunds(): bool;

    /** @param array<string, string> $headers */
    public function verifyWebhook(string $body, array $headers): bool;

    /**
     * Read a notice whose origin verifyWebhook() already accepted; a body that is not a notice of this provider is an
     * invalid value.
     */
    public function parseWebhook(string $body): PaymentEvent;
}
