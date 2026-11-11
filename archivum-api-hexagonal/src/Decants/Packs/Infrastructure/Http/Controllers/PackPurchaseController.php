<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Src\Decants\Packs\Application\UseCases\PurchaseDecantPack;
use Src\Decants\Packs\Infrastructure\Http\Requests\StorePackPurchase;
use Src\Decants\Packs\Infrastructure\Http\Resources\PurchasedPackResource;

final readonly class PackPurchaseController
{
    /**
     * Buy a decant pack on sale in the store of the address for the account of the session, and start its payment.
     *
     * The pack waits for its payment and becomes active only when a verified notice of the gateway or the
     * reconciliation confirms it, never with this answer or the return address of the gateway.
     */
    #[HeaderParameter(StorePackPurchase::IDEMPOTENCY_HEADER, 'Llave de 1 a 100 caracteres que el cliente elige para cada intento de compra. Repetir la compra con la misma llave responde 200 con el mismo paquete y su pago, sin un segundo cobro; la misma llave para otra oferta responde 409. Sin ella, cada solicitud es una compra nueva; vacía o más larga responde 400.', required: false, type: 'string')]
    #[Response(201, 'Se compra el paquete y se inicia su pago.', type: PurchasedPackResource::class)]
    #[Response(200, 'Se repitió la compra con la misma llave: se devuelve el mismo paquete y su pago.', type: PurchasedPackResource::class)]
    #[Response(202, 'Se compra el paquete, pero todavía no se sabe si la pasarela creó el cobro.', type: PurchasedPackResource::class)]
    public function store(StorePackPurchase $request, PurchaseDecantPack $purchase): JsonResponse
    {
        $purchased = $purchase->execute($request->toCommand());
        $response = (new PurchasedPackResource($purchased))->response($request)
            ->setStatusCode($purchased->created ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);

        // Like the creation of a payment (#92): the client asks again later, with the same key.
        if ($purchased->payment?->communicationError === true) {
            $response->headers->set('Retry-After', '30');
            $response->setStatusCode(JsonResponse::HTTP_ACCEPTED);
        }

        return $response;
    }
}
