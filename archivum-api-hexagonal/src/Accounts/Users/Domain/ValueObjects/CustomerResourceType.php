<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\ValueObjects;

/**
 * Closed list of the resources of a customer whose business fn_customer_resource_business can answer. Each schema
 * with resources of a customer adds its case here and its branch to the function: appointment with #59, order with #78.
 */
enum CustomerResourceType: string
{
    case Customer = 'customer';
    case Order = 'order';
    case Payment = 'payment';
}
