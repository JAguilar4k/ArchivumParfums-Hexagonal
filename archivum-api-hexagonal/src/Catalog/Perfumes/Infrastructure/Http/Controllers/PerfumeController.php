<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\PatchPerfume;
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

    /** Change the fields sent of a perfume of the business of the session, also to deactivate or reactivate it. */
    public function update(PatchPerfume $request, string $perfume, EditPerfume $editPerfume): PanelPerfume
    {
        return new PanelPerfume($editPerfume->execute($request->toCommand($perfume)));
    }
}
