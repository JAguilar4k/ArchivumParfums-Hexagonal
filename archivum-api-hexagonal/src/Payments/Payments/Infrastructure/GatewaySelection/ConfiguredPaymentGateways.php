<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\GatewaySelection;

use Illuminate\Contracts\Container\Container;
use Src\Payments\Payments\Domain\Contracts\PaymentGateway;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Infrastructure\Gateways\Onvo\OnvoGateway;
use Src\Payments\Payments\Infrastructure\Gateways\PayPal\PayPalGateway;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ConfiguredPaymentGateways implements PaymentGateways
{
    public function __construct(private Container $container) {}

    public function for(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'onvo' => $this->container->make(OnvoGateway::class),
            'paypal' => $this->container->make(PayPalGateway::class),
            default => throw new InvalidValueException('payments.invalid_configuration'),
        };
    }
}
