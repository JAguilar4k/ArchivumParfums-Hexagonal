<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Infrastructure\Http\Controllers;

use Src\Businesses\PublicProfile\Application\UseCases\GetPublicBusiness;
use Src\Businesses\PublicProfile\Infrastructure\Http\Resources\PublicBusiness;

final readonly class PublicBusinessController
{
    /** Show the public profile of the business that the slug of the address names. */
    public function show(GetPublicBusiness $getBusiness): PublicBusiness
    {
        return new PublicBusiness($getBusiness->execute());
    }
}
