<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Src\Payments\Payments\Application\UseCases\ProcessPaymentEvent;
use Src\Payments\Payments\Application\UseCases\ReceivePaymentEvent;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Infrastructure\Http\Resources\PaymentNoticeResource;
use Src\Payments\Payments\Infrastructure\Tenancy\PaymentEventBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final readonly class PaymentWebhookController
{
    /** Receive a notice of ONVO Pay, whose origin is the secret ONVO sends in `X-Webhook-Secret`. */
    #[Response(200, 'Aviso verificado y registrado con su resultado.', type: PaymentNoticeResource::class)]
    public function onvo(Request $request): PaymentNoticeResource
    {
        return $this->receive('onvo', $request);
    }

    /** Receive a notice of PayPal, whose signature PayPal's verification API checks. */
    #[Response(200, 'Aviso verificado y registrado con su resultado.', type: PaymentNoticeResource::class)]
    public function paypal(Request $request): PaymentNoticeResource
    {
        return $this->receive('paypal', $request);
    }

    /**
     * A notice has no session that names a business: the use cases are resolved only after the context of the notice
     * is bound, so every repository they use asks the business that ProcessPaymentEvent pins for the payment.
     */
    private function receive(string $gateway, Request $request): PaymentNoticeResource
    {
        app()->instance(BusinessContext::class, app(PaymentEventBusinessContext::class));
        $body = $request->getContent();

        try {
            $event = app(ReceivePaymentEvent::class)->execute($gateway, $body, $this->headers($request));
            $processed = app(ProcessPaymentEvent::class)->execute($event, $body);
        } catch (GatewayUnavailableException) {
            // A response other than 2xx makes the provider send the notice again later.
            throw new ServiceUnavailableHttpException(30);
        }

        return new PaymentNoticeResource($event, $processed);
    }

    /**
     * The first value of each header, by its lowercase name.
     *
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        return array_map(fn (array $values): string => (string) ($values[0] ?? ''), $request->headers->all());
    }
}
