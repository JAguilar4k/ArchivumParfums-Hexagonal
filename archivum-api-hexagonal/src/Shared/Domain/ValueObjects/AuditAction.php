<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

enum AuditAction: string
{
    case MemberAdded = 'member.added';
    case MemberRoleChanged = 'member.role_changed';
    case MemberDeactivated = 'member.deactivated';
    case MemberActivated = 'member.activated';
    case BottleAdjusted = 'bottle.adjusted';
    case ManualPriceChanged = 'variant.manual_price_changed';
    case RefundRequested = 'refund.requested';
    case ManualPaymentRecorded = 'payment.manual_recorded';
}
