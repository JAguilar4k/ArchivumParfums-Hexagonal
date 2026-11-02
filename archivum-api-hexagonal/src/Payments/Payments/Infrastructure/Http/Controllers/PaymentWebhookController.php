<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Payments\Payments\Application\UseCases\ReceivePaymentEvent;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final readonly class PaymentWebhookController
{
    /** Receive a notice of ONVO Pay, whose origin is the secret ONVO sends in `X-Webhook-Secret`. */
    #[Response(200, 'Aviso verificado. El primero se guarda y se procesa en la cola; uno repetido no cambia nada.', type: 'array{data: array{evento: string, estado: "recibido"|"repetido"}}')]
    public function onvo(Request $request, ReceivePaymentEvent $receive): JsonResponse
    {
        return $this->receive('onvo', $request, $receive);
    }

    /** Receive a notice of PayPal, whose signature PayPal's verification API checks. */
    #[Response(200, 'Aviso verificado. El primero se guarda y se procesa en la cola; uno repetido no cambia nada.', type: 'array{data: array{evento: string, estado: "recibido"|"repetido"}}')]
    public function paypal(Request $request, ReceivePaymentEvent $receive): JsonResponse
    {
        return $this->receive('paypal', $request, $receive);
    }

    /** The provider gets its answer once the notice is kept; ProcessPaymentEventJob applies it later. */
    private function receive(string $gateway, Request $request, ReceivePaymentEvent $receive): JsonResponse
    {
        try {
            $event = $receive->execute($gateway, $request->getContent(), $this->headers($request));
        } catch (GatewayUnavailableException) {
            // PayPal could not verify the signature, or the sync queue of the tests processed the notice in this
            // request while the provider could not be asked. Nothing was kept, and an answer other than 2xx makes the
            // provider send the notice again later.
            throw new ServiceUnavailableHttpException(30);
        }

        return response()->json(['data' => ['evento' => $event->eventId, 'estado' => $event->repeated ? 'repetido' : 'recibido']]);
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
