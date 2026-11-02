<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Payments\Payments\Application\DTOs\ReconcileRefundCommand;
use Src\Payments\Payments\Application\Services\RefundSettlement;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class ReconcileRefund
{
    /** Receive the port that pins the business of the refund, the settlement and the transaction of that business. */
    public function __construct(
        private BusinessScope $scope,
        private RefundSettlement $settlement,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Ask the provider, as the business of the refund, about a pending refund it already accepted and apply its real
     * state in one transaction, as its notice would (R40). Answer whether the refund stopped being pending; a refund
     * that is no longer pending, or that never got a reference of the provider, changes nothing.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function execute(ReconcileRefundCommand $command): bool
    {
        return $this->scope->run(new BusinessId($command->businessId), fn (): bool => $this->transaction->run(function () use ($command): bool {
            [$refund, $payment] = $this->settlement->lock(new RefundId($command->refundId)) ?? [null, null];
            if ($refund === null || $payment === null || $refund->status !== 'pending' || $refund->providerReference === null) {
                return false;
            }

            $this->settlement->settle($refund, $payment);

            return $refund->status !== 'pending';
        }));
    }
}
