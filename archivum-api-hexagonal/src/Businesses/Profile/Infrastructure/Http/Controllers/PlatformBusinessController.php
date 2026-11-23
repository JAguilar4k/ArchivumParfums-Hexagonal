<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Businesses\Profile\Application\UseCases\ListBusinesses;
use Src\Businesses\Profile\Application\UseCases\RegisterBusiness;
use Src\Businesses\Profile\Domain\Exceptions\SlugAlreadyInUseException;
use Src\Businesses\Profile\Infrastructure\Http\Requests\StorePlatformBusinessRequest;
use Src\Businesses\Profile\Infrastructure\Http\Resources\PlatformBusinessResource;

/**
 * Businesses of the whole platform, for its administrator (#347). No business is pinned here: businesses has no row
 * policy, and registering the owner pins the new business in its own transaction.
 */
final readonly class PlatformBusinessController
{
    /** List every business of the platform by name. */
    public function index(ListBusinesses $listBusinesses): AnonymousResourceCollection
    {
        return PlatformBusinessResource::collection($listBusinesses->execute());
    }

    /**
     * Register a business with the default settings and its owner. A slug or an owner email already in use answers 422
     * in its field, so the form can show it next to the control.
     *
     * @throws ValidationException
     */
    public function store(StorePlatformBusinessRequest $request, RegisterBusiness $registerBusiness): JsonResponse
    {
        try {
            $business = $registerBusiness->execute($request->toCommand());
        } catch (SlugAlreadyInUseException $exception) {
            throw ValidationException::withMessages(['slug' => __($exception->translationKey())]);
        } catch (EmailAlreadyRegisteredException $exception) {
            throw ValidationException::withMessages(['correo_dueno' => __($exception->translationKey())]);
        }

        return new PlatformBusinessResource($business)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
