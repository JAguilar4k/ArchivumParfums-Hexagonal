<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Infrastructure\Http\Controllers;

use Src\Businesses\Logo\Application\UseCases\UploadBusinessLogo;
use Src\Businesses\Logo\Infrastructure\Http\Requests\StoreBusinessLogo;
use Src\Businesses\Logo\Infrastructure\Http\Resources\BusinessLogoUrl;

final readonly class BusinessLogoController
{
    /** Keep the uploaded image as the logo of the business of the session. */
    public function store(StoreBusinessLogo $request, UploadBusinessLogo $uploadLogo): BusinessLogoUrl
    {
        return new BusinessLogoUrl($uploadLogo->execute($request->toCommand()));
    }
}
