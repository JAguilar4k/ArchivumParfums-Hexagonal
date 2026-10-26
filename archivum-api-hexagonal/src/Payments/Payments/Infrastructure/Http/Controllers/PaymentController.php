<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Application\UseCases\ObservePayment;
use Src\Payments\Payments\Application\UseCases\ProcessOrderRefunds;
use Src\Payments\Payments\Application\UseCases\StartPayment;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Infrastructure\Http\Requests\StorePayment;
use Src\Payments\Payments\Infrastructure\Http\Resources\PaymentResource;

final readonly class PaymentController
{
    /** Create a charge using only the amounts saved by the server. */
    #[Response(200, 'Se devuelve el intento de pago existente.', type: PaymentResource::class)]
    #[Response(201, 'Se crea un intento de pago.', type: PaymentResource::class)]
    #[Response(202, 'El resultado sigue pendiente de consulta al proveedor.', type: PaymentResource::class)]
    public function store(StorePayment $request, string $pedido, StartPayment $start): JsonResponse
    {
        $method = $request->validated('medio') === 'sinpe_movil' ? PaymentMethod::SinpeMobile : PaymentMethod::Card;
        $result = $start->execute($pedido, (string) $request->user()?->getAuthIdentifier(), $method);

        return $this->respond($result, $request, $result->created ? 201 : 200);
    }

    /** Confirm only by asking the provider from the server; the request carries no status. */
    #[Response(200, 'Estado del pago consultado desde el servidor.', type: PaymentResource::class)]
    #[Response(202, 'El resultado sigue pendiente de consulta al proveedor.', type: PaymentResource::class)]
    public function show(Request $request, string $pago, ObservePayment $observe): JsonResponse
    {
        return $this->respond($observe->execute($pago, (string) $request->user()?->getAuthIdentifier()), $request);
    }

    /** Process only refund amounts already registered by cancellation or an unavailable line. */
    public function refund(string $pedido, ProcessOrderRefunds $process): JsonResponse
    {
        return response()->json(['data' => $process->execute($pedido)]);
    }

    private function respond(PaymentResult $result, Request $request, int $status = 200): JsonResponse
    {
        $response = (new PaymentResource($result))->response()->setStatusCode($status);
        $response->headers->set('Location', route('payments.show', ['pago' => $result->id]));
        if ($result->communicationError) {
            $response->headers->set('Retry-After', '30');
            $response->setStatusCode(202);
        }

        return $response;
    }
}
