<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Src\Catalog\Perfumes\Application\UseCases\UploadPerfumePhoto;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\StorePerfumePhoto;
use Src\Catalog\Perfumes\Infrastructure\Http\Resources\PerfumePhotoUrl;

final readonly class PerfumePhotoController
{
    /** Keep the uploaded image as the photo of a perfume of the business of the session. */
    public function store(StorePerfumePhoto $request, string $perfume, UploadPerfumePhoto $uploadPhoto): PerfumePhotoUrl
    {
        return new PerfumePhotoUrl($uploadPhoto->execute($request->toCommand($perfume)));
    }
}
