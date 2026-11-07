<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Payments\Payments\Application\Contracts\PaymentBusinesses;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class ProcessPaymentEvent
{
    /** Receive the ports that find the business of a notice, pin it, apply the notice and record it. */
    public function __construct(
        private PaymentBusinesses $businesses,
        private BusinessScope $scope,
        private ApplyPaymentEvent $apply,
        private WebhookEventLog $log,
    ) {}

    /**
     * Apply a verified notice as the business of the payment it names and record the notice with its outcome. The
     * business is the one Archivum sent with the charge, when the notice brings it back, or the one of the provider's
     * reference. A notice that is not about a charge is recorded without changing anything, and one the payment
     * rejects is recorded with its error. When the provider cannot be asked, the failure is recorded and rethrown,
     * so that the provider sends the notice again.
     *
     * @return bool whether the notice was processed; false when it was recorded as failed
     *
     * @throws GatewayUnavailableException
     */
    public function execute(PaymentEvent $event, string $payload): bool
    {
        try {
            if ($event->aboutCharge()) {
                $this->scope->run($this->businessOf($event), fn () => $this->apply->execute($event));
            }
        } catch (GatewayUnavailableException $exception) {
            $this->log->record($event, $payload, $exception->translationKey());

            throw $exception;
        } catch (DomainException $exception) {
            $this->log->record($event, $payload, $exception->translationKey());

            return false;
        }

        $this->log->record($event, $payload);

        return true;
    }

    /** @throws PaymentNotFoundException when no payment has the notice's reference */
    private function businessOf(PaymentEvent $event): BusinessId
    {
        return $event->businessId
            ?? $this->businesses->ofReference($event->gateway, (string) $event->reference)
            ?? throw new PaymentNotFoundException('payments.not_found');
    }
}
