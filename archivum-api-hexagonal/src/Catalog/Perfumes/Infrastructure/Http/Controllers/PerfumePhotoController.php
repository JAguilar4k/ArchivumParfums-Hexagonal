<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Catalog\Perfumes\Application\UseCases\UploadPerfumePhoto;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\StorePerfumePhoto;

final readonly class PerfumePhotoController
{
    /** Keep the uploaded image as the photo of a perfume of the business of the session; the answer exposes only its address. */
    public function store(StorePerfumePhoto $request, string $perfume, UploadPerfumePhoto $uploadPhoto): JsonResponse
    {
        return new JsonResponse(['data' => ['foto_url' => $uploadPhoto->execute($request->toCommand($perfume))]]);
    }
}
