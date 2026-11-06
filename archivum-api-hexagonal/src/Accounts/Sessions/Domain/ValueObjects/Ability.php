<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\ValueObjects;

/**
 * Abilities a Sanctum token carries, fixed when it is issued. Each route declares the one it needs with the
 * abilities or ability middleware of Sanctum, so a token without it gets a 403 even when it knows the route.
 */
enum Ability: string
{
    /** Business, business hours, services and staff. */
    case BusinessConfigure = 'business:configure';

    /** Catalog, import, bottles, suppliers and purchase orders. */
    case CatalogManage = 'catalog:manage';

    case AppointmentsViewAll = 'appointments:view-all';

    case AppointmentsViewOwn = 'appointments:view-own';

    case AppointmentsAttendance = 'appointments:attendance';

    case AppointmentsBookForCustomer = 'appointments:book-for-customer';

    case OrdersManage = 'orders:manage';

    /** Payments on delivery and refunds. */
    case PaymentsRecord = 'payments:record';

    case PacksManage = 'packs:manage';

    case ReportsView = 'reports:view';

    /** Appointments, waitlist, orders, payments and packs of the customer. */
    case CustomerOwn = 'customer:own';

    /**
     * The only map from a role to its abilities, after the permission matrix in section 9 of the specification.
     * Whether a resource belongs to the account is up to the policies, not to these abilities.
     *
     * @return list<self>
     */
    public static function grantedTo(AccountRole $role): array
    {
        return match ($role) {
            AccountRole::Owner => [
                self::BusinessConfigure,
                self::CatalogManage,
                self::AppointmentsViewAll,
                self::AppointmentsAttendance,
                self::AppointmentsBookForCustomer,
                self::OrdersManage,
                self::PaymentsRecord,
                self::PacksManage,
                self::ReportsView,
            ],
            AccountRole::Staff => [
                self::AppointmentsViewOwn,
                self::AppointmentsAttendance,
            ],
            AccountRole::Customer => [
                self::CustomerOwn,
            ],
        };
    }

    /**
     * Names of the abilities of a role, as Sanctum stores them in the token.
     *
     * @return list<string>
     */
    public static function namesGrantedTo(AccountRole $role): array
    {
        return array_map(fn (self $ability): string => $ability->value, self::grantedTo($role));
    }
}
