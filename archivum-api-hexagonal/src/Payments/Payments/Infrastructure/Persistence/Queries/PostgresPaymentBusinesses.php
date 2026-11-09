<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Application\Contracts\PaymentBusinesses;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class PostgresPaymentBusinesses implements PaymentBusinesses
{
    /** Ask fn_payment_business, which reads payments before any business is pinned and answers only the business. */
    public function ofReference(string $gateway, string $reference): ?BusinessId
    {
        $businessId = DB::scalar('select fn_payment_business(?, ?)', [$gateway, $reference]);

        return is_string($businessId) ? new BusinessId($businessId) : null;
    }
}
