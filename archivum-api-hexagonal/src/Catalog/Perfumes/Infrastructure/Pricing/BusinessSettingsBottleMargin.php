<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Pricing;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Catalog\Perfumes\Application\Contracts\BottleMargin;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\Colones;

/** Read the margin per bottle from the settings of the business of the session, through the port of the Businesses module. */
final readonly class BusinessSettingsBottleMargin implements BottleMargin
{
    public function __construct(
        private BusinessContext $context,
        private BusinessRepository $businesses,
    ) {}

    public function current(): Colones
    {
        $business = $this->businesses->find($this->context->current());

        return new Colones($business?->settings->bottleMarginColones ?? BusinessSettings::DEFAULT_BOTTLE_MARGIN_COLONES);
    }
}
