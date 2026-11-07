<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PendingPaymentEvents;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Application\DTOs\ReceivedPaymentEvent;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\WebhookNotVerifiedException;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ReceivePaymentEvent
{
    /** Receive the gateways that verify and read their own notices, the log that keeps them and their processing. */
    public function __construct(
        private PaymentGateways $gateways,
        private WebhookEventLog $log,
        private PendingPaymentEvents $pending,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Accept a notice only when the provider verifies its origin (ONVO's secret header, PayPal's signature), before
     * anything is read or written, and read the event it carries. The answer to a notice of an unverified origin
     * never says which check failed; a gateway without its test credentials cannot verify any notice.
     *
     * The first arrival of an event is kept and left to be processed in one transaction, so a kept notice always has
     * its processing waiting; a repeated arrival changes nothing.
     *
     * @param  array<string, string>  $headers
     *
     * @throws WebhookNotVerifiedException
     * @throws GatewayUnavailableException when PayPal cannot verify the signature right now
     * @throws InvalidValueException when a verified body is not a notice of the provider
     */
    public function execute(string $gateway, string $body, array $headers): ReceivedPaymentEvent
    {
        try {
            $adapter = $this->gateways->for($gateway);
        } catch (InvalidValueException) {
            throw new WebhookNotVerifiedException('payments.webhook_not_verified');
        }

        if (! $adapter->verifyWebhook($body, $headers)) {
            throw new WebhookNotVerifiedException('payments.webhook_not_verified');
        }

        $event = $adapter->parseWebhook($body);

        return $this->transaction->run(function () use ($event, $body): ReceivedPaymentEvent {
            $id = $this->log->register($event, $body);
            if ($id !== null) {
                $this->pending->process($id);
            }

            return new ReceivedPaymentEvent($event->eventId, repeated: $id === null);
        });
    }
}
