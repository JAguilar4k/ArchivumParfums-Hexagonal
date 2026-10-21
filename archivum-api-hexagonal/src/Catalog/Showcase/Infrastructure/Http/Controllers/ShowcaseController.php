<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Catalog\Showcase\Application\UseCases\GetShowcase;
use Src\Catalog\Showcase\Application\UseCases\GetShowcasePerfume;
use Src\Catalog\Showcase\Infrastructure\Http\Requests\IndexShowcase;
use Src\Catalog\Showcase\Infrastructure\Http\Resources\ShowcasePerfume;
use Src\Catalog\Showcase\Infrastructure\Http\Resources\ShowcasePerfumeDetail;

final readonly class ShowcaseController
{
    /** Show a perfume of the showcase of the store of the address with its notes, tags and the presentations that can be sold. */
    public function show(Request $request, GetShowcasePerfume $getPerfume): ShowcasePerfumeDetail
    {
        // By name: the route also has {tienda}, which business:route already resolved, and the parameters of a route reach a method by position.
        return new ShowcasePerfumeDetail($getPerfume->execute((string) $request->route('perfume')));
    }

    /** Show one page of the showcase of the store of the address, filtered by what the visitor asked for. */
    public function index(IndexShowcase $request, GetShowcase $getShowcase): AnonymousResourceCollection
    {
        $page = $getShowcase->execute($request->toFilters());

        return ShowcasePerfume::collection(new LengthAwarePaginator(
            $page->items,
            $page->total,
            $page->perPage,
            $page->page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina'],
        ));
    }
}
