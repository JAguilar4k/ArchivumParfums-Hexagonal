<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Catalog\Perfumes\Application\UseCases\CreatePerfume;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfume;
use Src\Catalog\Perfumes\Application\UseCases\ListPerfumes;
use Src\Catalog\Perfumes\Application\UseCases\ShowPerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\IndexPerfumes;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\PatchPerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Requests\StorePerfume;
use Src\Catalog\Perfumes\Infrastructure\Http\Resources\PanelPerfume;

final readonly class PerfumeController
{
    /** List the perfumes of the business of the session, active and inactive, one page at a time. */
    public function index(IndexPerfumes $request, ListPerfumes $listPerfumes): AnonymousResourceCollection
    {
        $page = $listPerfumes->execute($request->toQuery());

        return PanelPerfume::collection(new LengthAwarePaginator(
            $page->items,
            $page->total,
            $page->perPage,
            $page->page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    /** Show a perfume of the business of the session with its notes, tags and presentations. */
    public function show(string $perfume, ShowPerfume $showPerfume): PanelPerfume
    {
        return new PanelPerfume($showPerfume->execute($perfume), withVariants: true);
    }

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
