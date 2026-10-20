<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\ValueObjects;

enum AccountRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Customer = 'customer';
}
