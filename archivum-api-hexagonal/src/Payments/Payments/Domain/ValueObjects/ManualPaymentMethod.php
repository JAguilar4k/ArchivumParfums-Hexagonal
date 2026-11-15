<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

/** How the customer paid the owner on delivery (R29). No gateway takes part in these payments. */
enum ManualPaymentMethod: string
{
    case Sinpe = 'sinpe';
    case Cash = 'cash';
}
