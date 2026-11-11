<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Decants\Packs\Application\UseCases\ListSoldDecantPacks;
use Src\Decants\Packs\Infrastructure\Http\Requests\IndexSoldPacks;
use Src\Decants\Packs\Infrastructure\Http\Resources\PanelSoldPack;

final readonly class SoldPackController
{
    /**
     * List a page of the decant packs sold in the business of the session, newest first, with their customer, their
     * offer, their balance and their status at this moment; estado keeps only the packs with that status, and vencido
     * also takes the ones whose validity ended before the queue expired them.
     */
    public function index(IndexSoldPacks $request, ListSoldDecantPacks $list): AnonymousResourceCollection
    {
        $page = $list->execute($request->filled('estado') ? $request->string('estado')->toString() : null, now()->toIso8601String(),
            $request->integer('pagina', 1), $request->integer('por_pagina', 20));

        return PanelSoldPack::collection(new LengthAwarePaginator($page->items, $page->total, $page->perPage, $page->page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina']));
    }
}
