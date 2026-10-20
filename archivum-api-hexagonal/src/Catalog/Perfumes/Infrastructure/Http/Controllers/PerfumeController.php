<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\PatchPerfume;
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
            throw $this->repeated($exception);
        }

        return (new PanelPerfume($perfume))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$perfume->id->value);
    }

    /**
     * Change the fields sent of a perfume of the business of the session, also to deactivate or reactivate it.
     *
     * @throws ValidationException
     */
    public function update(PatchPerfume $request, string $perfume, EditPerfume $editPerfume): PanelPerfume
    {
        try {
            return new PanelPerfume($editPerfume->execute($request->toCommand($perfume)));
        } catch (DuplicatePerfumeException $exception) {
            throw $this->repeated($exception);
        }
    }

    /** Report a repeated perfume on the field that names it. */
    private function repeated(DuplicatePerfumeException $exception): ValidationException
    {
        return ValidationException::withMessages([
            'nombre' => [__($exception->translationKey(), $exception->parameters())],
        ]);
    }
}
