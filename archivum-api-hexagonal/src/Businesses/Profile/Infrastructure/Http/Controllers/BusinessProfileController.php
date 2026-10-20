<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Http\Controllers;

use Src\Businesses\Profile\Application\UseCases\GetBusinessProfile;
use Src\Businesses\Profile\Application\UseCases\UpdateBusinessProfile;
use Src\Businesses\Profile\Infrastructure\Http\Requests\UpdateBusinessProfileRequest;
use Src\Businesses\Profile\Infrastructure\Http\Resources\BusinessProfileResource;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class BusinessProfileController
{
    /** Show the profile and the settings of the business of the session. */
    public function show(BusinessContext $businessContext, GetBusinessProfile $getProfile): BusinessProfileResource
    {
        return new BusinessProfileResource($getProfile->execute($businessContext->current()->value));
    }

    /** Change the profile fields and settings sent for the business of the session. */
    public function update(
        UpdateBusinessProfileRequest $request,
        BusinessContext $businessContext,
        UpdateBusinessProfile $updateProfile,
    ): BusinessProfileResource {
        return new BusinessProfileResource($updateProfile->execute($request->toCommand($businessContext)));
    }
}
