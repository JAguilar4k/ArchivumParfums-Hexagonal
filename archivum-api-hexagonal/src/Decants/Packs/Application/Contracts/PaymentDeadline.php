<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

interface PaymentDeadline
{
    /** Minutes a new pack waits for its payment before it expires, from the settings of the business of the session (R33). */
    public function minutes(): int;
}
