<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Tenancy;

use Closure;
use LogicException;
use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

/**
 * The business of a notice of the provider. The notice arrives with no session, so no business is known until the
 * verified event names its payment; ProcessPaymentEvent then runs the operation with that business pinned, and every
 * repository and transaction of the operation asks this context. The container keeps one per request.
 */
final class PaymentEventBusinessContext implements BusinessContext, BusinessScope
{
    private ?BusinessId $business = null;

    /** Answer the pinned business; outside run() no business is known. */
    public function current(): BusinessId
    {
        return $this->business ?? throw new LogicException('The business of a payment notice is known only while the notice is applied.');
    }

    public function run(BusinessId $business, Closure $operation): mixed
    {
        $previous = $this->business;
        $this->business = $business;

        try {
            return $operation();
        } finally {
            $this->business = $previous;
        }
    }
}
