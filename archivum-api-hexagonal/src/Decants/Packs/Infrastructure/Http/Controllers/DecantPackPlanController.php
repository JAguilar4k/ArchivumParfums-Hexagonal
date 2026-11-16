<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Src\Decants\Packs\Application\UseCases\CreateDecantPackPlan;
use Src\Decants\Packs\Application\UseCases\ListDecantPackPlans;
use Src\Decants\Packs\Application\UseCases\UpdateDecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantSizeNotSoldException;
use Src\Decants\Packs\Infrastructure\Http\Requests\PatchDecantPackPlan;
use Src\Decants\Packs\Infrastructure\Http\Requests\StoreDecantPackPlan;
use Src\Decants\Packs\Infrastructure\Http\Resources\PanelDecantPackPlan;

final readonly class DecantPackPlanController
{
    /** List the offers of decant packs of the business of the session, on sale or not, by name. */
    public function index(ListDecantPackPlans $listPlans): AnonymousResourceCollection
    {
        return PanelDecantPackPlan::collection($listPlans->execute());
    }

    /**
     * Define an offer of decant packs in the business of the session.
     *
     * Ml that the store does not sell as decants are reported on the field ml_por_decant.
     *
     * @throws ValidationException
     */
    public function store(StoreDecantPackPlan $request, CreateDecantPackPlan $createPlan): JsonResponse
    {
        try {
            $plan = $createPlan->execute($request->toCommand());
        } catch (DecantSizeNotSoldException $exception) {
            throw $this->sizeNotSold($exception);
        }

        return (new PanelDecantPackPlan($plan))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$plan->id->value);
    }

    /**
     * Change the fields sent of an offer of the business of the session, also to take it off sale or put it back.
     *
     * The packs already bought keep the decants, the ml and the cap they were bought with.
     *
     * @throws ValidationException
     */
    public function update(PatchDecantPackPlan $request, string $paquete, UpdateDecantPackPlan $updatePlan): PanelDecantPackPlan
    {
        try {
            return new PanelDecantPackPlan($updatePlan->execute($request->toCommand($paquete)));
        } catch (DecantSizeNotSoldException $exception) {
            throw $this->sizeNotSold($exception);
        }
    }

    /** Report ml the store does not sell on the field that names them. */
    private function sizeNotSold(DecantSizeNotSoldException $exception): ValidationException
    {
        return ValidationException::withMessages([
            'ml_por_decant' => [__($exception->translationKey(), $exception->parameters())],
        ]);
    }
}
