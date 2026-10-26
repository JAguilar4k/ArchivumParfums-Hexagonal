<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Decants\Bottles\Application\UseCases\AdjustDecantBottle;
use Src\Decants\Bottles\Application\UseCases\GetDecantBottle;
use Src\Decants\Bottles\Application\UseCases\ListDecantMovements;
use Src\Decants\Bottles\Infrastructure\Http\Requests\AdjustBottle;
use Src\Decants\Bottles\Infrastructure\Http\Requests\IndexBottles;
use Src\Decants\Bottles\Infrastructure\Http\Resources\PanelBottle;
use Src\Decants\Bottles\Infrastructure\Http\Resources\PanelMovement;

final readonly class DecantMovementController
{
    public function index(IndexBottles $request, string $botella, ListDecantMovements $list): AnonymousResourceCollection
    {
        $page = $list->execute($botella, $request->integer('pagina', 1), $request->integer('por_pagina', 20));

        return PanelMovement::collection(new LengthAwarePaginator($page->items, $page->total, $page->perPage, $page->page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina']));
    }

    public function store(AdjustBottle $request, string $botella, AdjustDecantBottle $adjust, GetDecantBottle $get): JsonResponse
    {
        $movement = $adjust->execute($botella, $request->integer('ml'), $request->string('motivo')->toString(), (string) $request->user()?->getAuthIdentifier());

        return new JsonResponse(['data' => ['movimiento' => new PanelMovement($movement)->toArray($request), 'botella' => new PanelBottle($get->execute($botella))->toArray($request)]], 201);
    }
}
