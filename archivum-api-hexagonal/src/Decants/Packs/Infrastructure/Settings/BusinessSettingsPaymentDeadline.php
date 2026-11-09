<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Settings;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Decants\Packs\Application\Contracts\PaymentDeadline;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Read the payment deadline from the settings of the business of the session, the same one an unpaid order has. */
final readonly class BusinessSettingsPaymentDeadline implements PaymentDeadline
{
    public function __construct(
        private BusinessContext $context,
        private BusinessRepository $businesses,
    ) {}

    public function minutes(): int
    {
        $business = $this->businesses->find($this->context->current());

        return $business?->settings->unpaidExpiryMinutes ?? BusinessSettings::DEFAULT_UNPAID_EXPIRY_MINUTES;
    }
}
