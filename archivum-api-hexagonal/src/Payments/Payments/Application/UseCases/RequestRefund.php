<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Payments\Payments\Application\DTOs\RequestRefundCommand;
use Src\Payments\Payments\Application\Services\RefundSettlement;
use Src\Payments\Payments\Domain\Contracts\PaymentGateway;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class RequestRefund
{
    /** Receive the ports that lock and keep the refund, the provider of its payment and the transaction of its business. */
    public function __construct(
        private BusinessScope $scope,
        private RefundRepository $refunds,
        private PaymentGateways $gateways,
        private RefundSettlement $settlement,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Send a registered refund to the provider of its original payment, as the business of the refund, and apply what
     * the provider answers (R40). Its attempt is committed before the request, so a refund that already left is not
     * sent again blindly. When the answer is lost, a provider that recognizes the same request (PayPal) is asked again
     * by the retries of the queue, with the same amount; with any other (ONVO) the refund stays pending without the
     * provider's reference, for a person to check in the provider. One the provider accepted and still processes is
     * confirmed by its notice or by the reconciliation (#96), and one it refused fails.
     *
     * @throws GatewayUnavailableException when a provider that recognizes the same request did not answer, so the
     *                                     queue sends it again
     */
    public function execute(RequestRefundCommand $command): void
    {
        $this->scope->run(new BusinessId($command->businessId), function () use ($command): void {
            $id = new RefundId($command->refundId);
            $attempt = $this->transaction->run(fn (): ?array => $this->attempt($id));
            if ($attempt === null) {
                return;
            }

            [$gateway, $request] = $attempt;
            try {
                $answer = $gateway->refund($request);
            } catch (GatewayUnavailableException $exception) {
                if ($gateway->resendsRefunds()) {
                    throw $exception;
                }

                return;
            }

            // Keep the provider's reference on its own first: it survives even if applying the answer fails.
            $reference = $answer->providerReference;
            if ($reference !== null) {
                $this->transaction->run(function () use ($id, $reference): void {
                    $refund = $this->refunds->find($id, true);
                    if ($refund !== null && $refund->status === 'pending' && $refund->providerReference === null) {
                        $refund->record($reference, 'pending');
                        $this->refunds->save($refund);
                    }
                });
            }

            $this->transaction->run(function () use ($id, $answer): void {
                [$refund, $payment] = $this->settlement->lock($id) ?? [null, null];
                if ($refund !== null && $payment !== null && $refund->status === 'pending') {
                    $this->settlement->apply($refund, $payment, $answer);
                }
            });
        });
    }

    /**
     * Under the locks of the refund, commit the attempt of a refund that never left, with its amount in the unit the
     * provider charged: the running total of the payment's refunds that already left decides it (R37). A refund that
     * left is sent again only while the provider has not answered it and only to a provider that recognizes the same
     * request, with the amount it left with. Answer the provider and the request to send, or null when there is
     * nothing to send.
     *
     * @return array{PaymentGateway, RefundRequest}|null
     */
    private function attempt(RefundId $id): ?array
    {
        [$refund, $payment] = $this->settlement->lock($id) ?? [null, null];
        if ($refund === null || $payment === null || $refund->status !== 'pending') {
            return null;
        }

        $gateway = $this->gateways->for($payment->gateway);
        if ($refund->attempted) {
            return $refund->providerReference === null && $gateway->resendsRefunds() ? [$gateway, $this->settlement->requestFor($refund, $payment)] : null;
        }

        $refundedBefore = $payment->refundedAmount + $this->refunds->inFlightAmountOf($payment->id);
        $refund->start($gateway->refundAmount($refund->amount, $refundedBefore, $payment->exchangeRate));
        $this->refunds->save($refund);

        return [$gateway, $this->settlement->requestFor($refund, $payment)];
    }
}
