<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Payments\Payments\Application\Contracts\PaymentBusinesses;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Throwable;

final readonly class ProcessPaymentEvent
{
    /** Receive the ports that read a kept notice, find and pin its business, apply it and record its outcome. */
    public function __construct(
        private WebhookEventLog $log,
        private PaymentGateways $gateways,
        private PaymentBusinesses $businesses,
        private BusinessScope $scope,
        private ApplyPaymentEvent $apply,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply a kept notice as the business of the payment it names and mark it processed in one transaction: both
     * happen or neither does. The business is the one Archivum sent with the charge, when the notice brings it back,
     * or the one of the provider's reference. A notice that is not about a charge is marked processed without
     * changing anything, and one already processed is left as it is, so a new attempt never applies it twice.
     *
     * A notice that fails is marked failed with the key of its error. When the provider cannot be asked, or the error
     * is unexpected, the error is thrown again so the queue tries later; an error of the notice itself, such as an
     * amount that is not the payment's, would only repeat, so the notice stays failed.
     *
     * @throws GatewayUnavailableException
     */
    public function execute(string $id): void
    {
        try {
            $this->transaction->run(function () use ($id): void {
                $notice = $this->log->lockForProcessing($id);
                if ($notice === null) {
                    return;
                }

                $event = $this->gateways->for($notice->gateway)->parseWebhook($notice->payload);
                if ($event->aboutCharge()) {
                    $this->scope->run($this->businessOf($event), fn () => $this->apply->execute($event));
                }

                $this->log->markProcessed($id);
            });
        } catch (DomainException $exception) {
            $this->log->markFailed($id, $exception->translationKey());

            if ($exception instanceof GatewayUnavailableException) {
                throw $exception;
            }
        } catch (Throwable $exception) {
            $this->log->markFailed($id, 'payments.event_not_processed');

            throw $exception;
        }
    }

    /** @throws PaymentNotFoundException when no payment has the notice's reference */
    private function businessOf(PaymentEvent $event): BusinessId
    {
        return $event->businessId
            ?? $this->businesses->ofReference($event->gateway, (string) $event->reference)
            ?? throw new PaymentNotFoundException('payments.not_found');
    }
}
