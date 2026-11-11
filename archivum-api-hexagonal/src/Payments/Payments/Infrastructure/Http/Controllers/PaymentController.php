<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Application\UseCases\ObservePayment;
use Src\Payments\Payments\Application\UseCases\RecordManualPayment;
use Src\Payments\Payments\Application\UseCases\RequestOrderRefunds;
use Src\Payments\Payments\Application\UseCases\StartPayment;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Infrastructure\Http\Requests\StoreManualPayment;
use Src\Payments\Payments\Infrastructure\Http\Requests\StorePayment;
use Src\Payments\Payments\Infrastructure\Http\Resources\ManualPaymentResource;
use Src\Payments\Payments\Infrastructure\Http\Resources\PaymentResource;
use Src\Payments\Payments\Infrastructure\Http\Resources\RefundResource;

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

    /** Read the stored payment: this query never asks the provider nor changes the payment, and the request carries no status. */
    #[Response(200, 'Estado guardado del pago. Mientras siga pendiente, el cliente muestra "procesando".', type: PaymentResource::class)]
    #[Response(202, 'Todavía no se sabe si el proveedor creó el cobro.', type: PaymentResource::class)]
    public function show(Request $request, string $pago, ObservePayment $observe): JsonResponse
    {
        return $this->respond($observe->execute($pago, (string) $request->user()?->getAuthIdentifier()), $request);
    }

    /** Record the balance that the customer paid the owner on delivery, by SINPE or in cash; no gateway takes part. */
    #[Response(201, 'Se registra el pago del saldo, ya confirmado.', type: ManualPaymentResource::class)]
    public function storeManual(StoreManualPayment $request, RecordManualPayment $record): JsonResponse
    {
        return (new ManualPaymentResource($record->execute($request->toCommand())))->response($request)->setStatusCode(201);
    }

    /**
     * List the refunds of an order with their state and ask the provider again for those that never left.
     *
     * Only a cancellation, an unavailable line (R30, R31) or a charge confirmed after the order closed registers a
     * refund, with the amount the server calculates.
     */
    public function refund(string $pedido, RequestOrderRefunds $request): AnonymousResourceCollection
    {
        return RefundResource::collection($request->execute($pedido));
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
