<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Catalog\Showcase\Application\UseCases\GetShowcase;
use Src\Catalog\Showcase\Infrastructure\Http\Requests\IndexShowcase;
use Src\Catalog\Showcase\Infrastructure\Http\Resources\ShowcasePerfume;

final readonly class ShowcaseController
{
    /** Show one page of the showcase of the store of the address, filtered by what the visitor asked for. */
    public function index(IndexShowcase $request, GetShowcase $getShowcase): AnonymousResourceCollection
    {
        $page = $getShowcase->execute($request->toFilters());

        return ShowcasePerfume::collection(new LengthAwarePaginator(
            $page->items,
            $page->total,
            $page->perPage,
            $page->page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }
}
