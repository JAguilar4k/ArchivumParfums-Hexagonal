<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

enum PaymentMethod: string
{
    case Card = 'card';
    case SinpeMobile = 'sinpe_mobile';
}
