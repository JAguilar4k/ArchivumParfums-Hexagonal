<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\StorePerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Resources\PanelPerfume;

final readonly class PerfumeController
{
    /**
     * Register a perfume in the business of the session; a repeated one is reported on the field that names it.
     *
     * @throws ValidationException
     */
    public function store(StorePerfume $request, CreatePerfume $createPerfume): JsonResponse
    {
        try {
            $perfume = $createPerfume->execute($request->toCommand());
        } catch (DuplicatePerfumeException $exception) {
            throw ValidationException::withMessages([
                'nombre' => [__($exception->translationKey(), $exception->parameters())],
            ]);
        }

        return (new PanelPerfume($perfume))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$perfume->id->value);
    }
}
