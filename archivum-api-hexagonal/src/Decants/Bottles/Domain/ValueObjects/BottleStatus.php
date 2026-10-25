<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\ValueObjects;

enum BottleStatus: string
{
    case Open = 'open';
    case Empty = 'empty';
}
