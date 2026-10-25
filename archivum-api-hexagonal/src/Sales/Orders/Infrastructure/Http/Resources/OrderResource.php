<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        assert($order instanceof Order);
        $remainingDue = $order->amounts->dueNow - $order->amounts->paid;

        return ['id' => $order->id->value, 'tienda' => ['id' => $order->businessId->value, 'nombre' => $order->businessName],
            'cliente' => ['id' => $order->customerId, 'nombre' => $order->customerName], 'estado' => $order->status->spanish(),
            'subtotal' => $order->amounts->subtotal, 'por_cobrar_ahora' => $order->status === OrderStatus::PendingPayment && $remainingDue > 0 ? $remainingDue : 0,
            'pagado' => $order->amounts->paid, 'saldo' => $order->amounts->balance, 'creado_en' => $order->createdAt->format(DATE_ATOM),
            'vence_en' => $order->status === OrderStatus::PendingPayment ? $order->expiresAt->format(DATE_ATOM) : null,
            'cancelado_en' => $order->cancelledAt?->format(DATE_ATOM),
            'lineas' => OrderLineResource::collection($order->lines)];
    }
}
