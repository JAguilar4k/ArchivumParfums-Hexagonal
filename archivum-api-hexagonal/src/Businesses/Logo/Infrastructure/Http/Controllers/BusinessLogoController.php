<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Businesses\Logo\Application\UseCases\UploadBusinessLogo;
use Src\Businesses\Logo\Infrastructure\Http\Requests\StoreBusinessLogo;

final readonly class BusinessLogoController
{
    /** Keep the uploaded image as the logo of the business of the session; the answer exposes only its address. */
    public function store(StoreBusinessLogo $request, UploadBusinessLogo $uploadLogo): JsonResponse
    {
        return new JsonResponse(['data' => ['logo_url' => $uploadLogo->execute($request->toCommand())]]);
    }
}
