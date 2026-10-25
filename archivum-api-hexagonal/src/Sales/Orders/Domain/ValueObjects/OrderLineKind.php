<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\ValueObjects;

enum OrderLineKind: string
{
    case Bottle = 'bottle';
    case Decant = 'decant';
}
