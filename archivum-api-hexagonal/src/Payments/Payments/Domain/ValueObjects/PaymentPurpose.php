<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

enum PaymentPurpose: string
{
    case Deposit = 'deposit';
    case Balance = 'balance';
    case Full = 'full';
}
