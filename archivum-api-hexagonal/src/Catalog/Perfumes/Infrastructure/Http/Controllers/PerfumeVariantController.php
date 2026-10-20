<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Src\Catalog\Perfumes\Application\UseCases\AddPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\ListPerfumeVariants;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\PatchPerfumeVariant;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\StorePerfumeVariant;
use Src\Catalog\Perfumes\Infrastructure\Http\Resources\PanelPerfumeVariant;

final readonly class PerfumeVariantController
{
    /** List every presentation of a perfume of the business of the session, active or not. */
    public function index(string $perfume, ListPerfumeVariants $listVariants): AnonymousResourceCollection
    {
        return PanelPerfumeVariant::collection($listVariants->execute($perfume));
    }

    /**
     * Add a presentation to a perfume; repeated milliliters are reported on the field ml.
     *
     * @throws ValidationException
     */
    public function store(StorePerfumeVariant $request, string $perfume, AddPerfumeVariant $addVariant): JsonResponse
    {
        try {
            $variant = $addVariant->execute($request->toCommand($perfume));
        } catch (DuplicateVariantSizeException $exception) {
            throw $this->repeated($exception);
        }

        return (new PanelPerfumeVariant($variant))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$variant->id->value);
    }

    /**
     * Change the milliliters, the manual price or the state of a presentation; repeated milliliters are reported on the field ml.
     *
     * @throws ValidationException
     */
    public function update(PatchPerfumeVariant $request, string $perfume, string $presentacion, EditPerfumeVariant $editVariant): PanelPerfumeVariant
    {
        try {
            return new PanelPerfumeVariant($editVariant->execute($request->toCommand($perfume, $presentacion)));
        } catch (DuplicateVariantSizeException $exception) {
            throw $this->repeated($exception);
        }
    }

    /** Report repeated milliliters on the field that names them. */
    private function repeated(DuplicateVariantSizeException $exception): ValidationException
    {
        return ValidationException::withMessages([
            'ml' => [__($exception->translationKey(), $exception->parameters())],
        ]);
    }
}
