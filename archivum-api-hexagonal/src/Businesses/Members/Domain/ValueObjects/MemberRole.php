<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\ValueObjects;

enum MemberRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
