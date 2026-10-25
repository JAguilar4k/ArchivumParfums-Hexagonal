<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
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

    public function refund(RefundRequest $request): RefundResult;

    /** @param array<string, string> $headers */
    public function verifyWebhook(string $body, array $headers): bool;
}
