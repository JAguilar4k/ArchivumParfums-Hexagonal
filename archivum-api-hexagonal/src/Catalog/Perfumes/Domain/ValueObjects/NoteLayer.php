<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

enum NoteLayer: string
{
    case Top = 'top';
    case Heart = 'heart';
    case Base = 'base';
}
