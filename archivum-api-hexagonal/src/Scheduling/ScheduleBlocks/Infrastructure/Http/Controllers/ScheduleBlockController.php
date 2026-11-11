<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Scheduling\ScheduleBlocks\Application\UseCases\CreateScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Application\UseCases\DeleteScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Application\UseCases\EditScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Application\UseCases\ListScheduleBlocks;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests\IndexScheduleBlocks;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests\PatchScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests\StoreScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Resources\PanelScheduleBlock;

final readonly class ScheduleBlockController
{
    /** List the blocks of the business of the session that cross the range asked for. */
    public function index(IndexScheduleBlocks $request, ListScheduleBlocks $listScheduleBlocks): AnonymousResourceCollection
    {
        return PanelScheduleBlock::collection($listScheduleBlocks->execute($request->toFilters()));
    }

    /** Block a range of the agenda, for one person or for the whole store. */
    public function store(StoreScheduleBlock $request, CreateScheduleBlock $createScheduleBlock): JsonResponse
    {
        $block = $createScheduleBlock->execute($request->toCommand());

        return (new PanelScheduleBlock($block))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$block->id->value);
    }

    /** Change the fields sent of a block of the agenda. */
    public function update(PatchScheduleBlock $request, string $bloqueo, EditScheduleBlock $editScheduleBlock): PanelScheduleBlock
    {
        return new PanelScheduleBlock($editScheduleBlock->execute($request->toCommand($bloqueo)));
    }

    /** Delete a block of the agenda. */
    public function destroy(string $bloqueo, DeleteScheduleBlock $deleteScheduleBlock): Response
    {
        $deleteScheduleBlock->execute($bloqueo);

        return response()->noContent();
    }
}
