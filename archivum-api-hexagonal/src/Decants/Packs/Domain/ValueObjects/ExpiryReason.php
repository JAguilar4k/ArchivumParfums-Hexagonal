<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

/** Why a pack expires (#159): nobody paid it before its deadline, or its validity ended while it was active (R33, R35). */
enum ExpiryReason: string
{
    case Unpaid = 'unpaid';
    case ValidityEnded = 'validity_ended';

    /** The status of the packs this reason expires: a pack that waits for its payment, or an active one. */
    public function status(): DecantPackStatus
    {
        return match ($this) {
            self::Unpaid => DecantPackStatus::PendingPayment,
            self::ValidityEnded => DecantPackStatus::Active,
        };
    }
}
