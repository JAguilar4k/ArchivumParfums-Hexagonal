<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\WebhookNotVerifiedException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ReceivePaymentEvent
{
    /** Receive the gateways that verify and read their own notices. */
    public function __construct(private PaymentGateways $gateways) {}

    /**
     * Accept a notice only when the provider verifies its origin (ONVO's secret header, PayPal's signature), before
     * anything is read or written, and read the event it carries. The answer to a notice of an unverified origin
     * never says which check failed; a gateway without its test credentials cannot verify any notice.
     *
     * @param  array<string, string>  $headers
     *
     * @throws WebhookNotVerifiedException
     * @throws GatewayUnavailableException when PayPal cannot verify the signature right now
     * @throws InvalidValueException when a verified body is not a notice of the provider
     */
    public function execute(string $gateway, string $body, array $headers): PaymentEvent
    {
        try {
            $adapter = $this->gateways->for($gateway);
        } catch (InvalidValueException) {
            throw new WebhookNotVerifiedException('payments.webhook_not_verified');
        }

        if (! $adapter->verifyWebhook($body, $headers)) {
            throw new WebhookNotVerifiedException('payments.webhook_not_verified');
        }

        return $adapter->parseWebhook($body);
    }
}
