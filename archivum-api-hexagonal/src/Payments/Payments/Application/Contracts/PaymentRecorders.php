<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Payments\Payments\Application\DTOs\PaymentRecorder;

interface PaymentRecorders
{
    /**
     * Find the owner of the pinned business who signs in with the account: only an owner records the payments received
     * on delivery (R29), so any other account is forbidden, even with a token that still carries the ability.
     */
    public function ownerOf(string $userId): PaymentRecorder;
}
