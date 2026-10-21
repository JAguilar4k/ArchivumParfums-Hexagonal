<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\StorePerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Resources\PanelPerfume;

final readonly class PerfumeController
{
    /** Register a perfume in the business of the session. */
    public function store(StorePerfume $request, CreatePerfume $createPerfume): JsonResponse
    {
        $perfume = $createPerfume->execute($request->toCommand());

        return (new PanelPerfume($perfume))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$perfume->id->value);
    }
}
