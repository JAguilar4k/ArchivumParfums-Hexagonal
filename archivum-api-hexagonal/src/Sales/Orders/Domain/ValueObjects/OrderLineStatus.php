<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\ValueObjects;

enum OrderLineStatus: string
{
    case Pending = 'pending';
    case Ordered = 'ordered';
    case Received = 'received';
    case Unavailable = 'unavailable';
    case Reserved = 'reserved';
    case Prepared = 'prepared';
    case Released = 'released';

    /** Stable Spanish vocabulary of the public API. */
    public function spanish(): string
    {
        return match ($this) {
            self::Pending => 'pendiente', self::Ordered => 'encargada', self::Received => 'recibida',
            self::Unavailable => 'no_disponible', self::Reserved => 'reservada',
            self::Prepared => 'preparada', self::Released => 'liberada',
        };
    }
}
