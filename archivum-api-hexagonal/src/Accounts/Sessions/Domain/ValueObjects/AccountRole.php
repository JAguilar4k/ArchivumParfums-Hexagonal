<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\ValueObjects;

enum AccountRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Customer = 'customer';

    /** Administers the platform: creates and lists businesses, and belongs to none of them (#347). */
    case PlatformAdmin = 'platform_admin';

    /**
     * The only rule that gives an account its role: the mark of the platform first, then the role of its staff
     * membership and, without either, customer.
     */
    public static function resolve(bool $isPlatformAdmin, ?self $staffRole): self
    {
        if ($isPlatformAdmin) {
            return self::PlatformAdmin;
        }

        return $staffRole ?? self::Customer;
    }
}
