<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\ValueObjects;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Ordered = 'ordered';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Stable Spanish vocabulary of the public API. */
    public function spanish(): string
    {
        return match ($this) {
            self::PendingPayment => 'pendiente_de_pago', self::Confirmed => 'confirmado',
            self::Ordered => 'encargado', self::Ready => 'listo', self::Delivered => 'entregado',
            self::Cancelled => 'cancelado', self::Expired => 'vencido',
        };
    }

    /** Parse only the documented API values. */
    public static function fromSpanish(string $value): ?self
    {
        foreach (self::cases() as $status) {
            if ($status->spanish() === $value) {
                return $status;
            }
        }

        return null;
    }
}
