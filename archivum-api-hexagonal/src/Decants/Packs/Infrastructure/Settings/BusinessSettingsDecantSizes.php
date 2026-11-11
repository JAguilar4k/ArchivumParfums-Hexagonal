<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Settings;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Decants\Packs\Application\Contracts\DecantSizes;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Read the decant sizes from the settings of the business of the session, through the port of the Businesses module. */
final readonly class BusinessSettingsDecantSizes implements DecantSizes
{
    public function __construct(
        private BusinessContext $context,
        private BusinessRepository $businesses,
    ) {}

    public function current(): array
    {
        $business = $this->businesses->find($this->context->current());

        return $business?->settings->decantSizesMl ?? BusinessSettings::DEFAULT_DECANT_SIZES_ML;
    }
}
