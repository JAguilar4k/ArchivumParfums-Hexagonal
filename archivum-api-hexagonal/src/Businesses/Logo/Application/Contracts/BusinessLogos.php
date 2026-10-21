<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Application\Contracts;

use Src\Shared\Domain\ValueObjects\BusinessId;

interface BusinessLogos
{
    /** The path of the logo a business has now, or null when it has none. */
    public function pathOf(BusinessId $business): ?string;

    /** Keep the path of the new logo of a business. */
    public function save(BusinessId $business, string $path): void;
}
