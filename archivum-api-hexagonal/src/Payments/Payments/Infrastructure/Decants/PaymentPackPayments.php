<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Decants;

use Src\Decants\Packs\Application\Contracts\PackPayments;
use Src\Decants\Packs\Application\DTOs\PackPayment;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Application\UseCases\StartPayment;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;

/** The payments of the decant packs, started with the same use case as the payment of an order (#92). */
final readonly class PaymentPackPayments implements PackPayments
{
    public function __construct(private StartPayment $startPayment, private PaymentRepository $payments) {}

    public function start(string $packId, string $userId, string $method): PackPayment
    {
        return $this->packPaymentOf($this->startPayment->execute(new Payable('decant_pack', $packId), $userId, PaymentMethod::from($method)));
    }

    public function last(string $packId): ?PackPayment
    {
        $payment = $this->payments->last(new Payable('decant_pack', $packId));

        // Like the query of a payment: a pending attempt without a reference of the provider still does not know whether its charge was created.
        return $payment === null ? null : $this->packPaymentOf(PaymentResult::from($payment, communicationError: $payment->status === PaymentStatus::Pending && $payment->providerReference === null));
    }

    private function packPaymentOf(PaymentResult $result): PackPayment
    {
        return new PackPayment($result->id, $result->amount, $result->currency, $result->chargedAmount, $result->gateway, $result->status, $result->checkoutUrl, $result->method, $result->communicationError);
    }
}
