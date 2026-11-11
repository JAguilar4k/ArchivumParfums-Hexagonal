<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

enum DecantPackStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Expired = 'expired';

    /** Stable Spanish vocabulary of the public API, as in the table of docs/ubiquitous-language.md. */
    public function spanish(): string
    {
        return match ($this) {
            self::PendingPayment => 'pendiente_de_pago', self::Active => 'activo',
            self::Exhausted => 'agotado', self::Expired => 'vencido',
        };
    }
}
