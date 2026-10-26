<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Src\Decants\Bottles\Application\UseCases\GetDecantBottle;
use Src\Decants\Bottles\Application\UseCases\ListDecantBottles;
use Src\Decants\Bottles\Application\UseCases\OpenDecantBottle;
use Src\Decants\Bottles\Application\UseCases\UpdateDecantBottleNotes;
use Src\Decants\Bottles\Infrastructure\Http\Requests\IndexBottles;
use Src\Decants\Bottles\Infrastructure\Http\Requests\PatchDecantBottle;
use Src\Decants\Bottles\Infrastructure\Http\Requests\StoreDecantBottle;
use Src\Decants\Bottles\Infrastructure\Http\Resources\PanelBottle;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DecantBottleController
{
    public function index(IndexBottles $request, ListDecantBottles $list): AnonymousResourceCollection
    {
        $page = $list->execute($request->integer('pagina', 1), $request->integer('por_pagina', 20));

        return PanelBottle::collection(new LengthAwarePaginator($page->items, $page->total, $page->perPage, $page->page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina']));
    }

    public function show(string $botella, GetDecantBottle $get): PanelBottle
    {
        return new PanelBottle($get->execute($botella));
    }

    public function store(StoreDecantBottle $request, OpenDecantBottle $open): JsonResponse
    {
        try {
            $bottle = $open->execute($request->toCommand());
        } catch (InvalidValueException $exception) {
            $field = match ($exception->translationKey()) {
                'bottles.capacity_mismatch' => 'ml_totales',
                'bottles.remaining_invalid', 'bottles.invalid_ml' => 'ml_restantes',
                default => 'presentacion_id',
            };
            throw ValidationException::withMessages([$field => [__($exception->translationKey())]]);
        }

        return new PanelBottle($bottle)->response($request)->setStatusCode(201)->header('Location', rtrim($request->url(), '/').'/'.$bottle->id);
    }

    public function update(PatchDecantBottle $request, string $botella, UpdateDecantBottleNotes $update, GetDecantBottle $get): PanelBottle
    {
        if (! array_key_exists('notas', $request->validated())) {
            return new PanelBottle($get->execute($botella));
        }

        $notes = $request->validated('notas');

        return new PanelBottle($update->execute($botella, is_string($notes) ? $notes : null));
    }
}
