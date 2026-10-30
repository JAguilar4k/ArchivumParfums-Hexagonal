<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Businesses\Members\Application\UseCases\GetMember;
use Src\Businesses\Members\Application\UseCases\ListMembers;
use Src\Businesses\Members\Application\UseCases\RegisterMember;
use Src\Businesses\Members\Application\UseCases\UpdateMember;
use Src\Businesses\Members\Infrastructure\Http\Requests\StoreMemberRequest;
use Src\Businesses\Members\Infrastructure\Http\Requests\UpdateMemberRequest;
use Src\Businesses\Members\Infrastructure\Http\Resources\MemberResource;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class MemberController
{
    /** List the staff of the business of the session. */
    public function index(BusinessContext $businessContext, ListMembers $listMembers): AnonymousResourceCollection
    {
        return MemberResource::collection($listMembers->execute($businessContext->current()->value));
    }

    /**
     * Add a person to the staff of the business of the session. An email already registered answers 422 in the field,
     * with a message that does not say where it is registered.
     *
     * @throws ValidationException
     */
    public function store(StoreMemberRequest $request, BusinessContext $businessContext, RegisterMember $registerMember): JsonResponse
    {
        try {
            $member = $registerMember->execute($request->toCommand($businessContext));
        } catch (EmailAlreadyRegisteredException $exception) {
            throw ValidationException::withMessages(['correo' => __($exception->translationKey())]);
        }

        return new MemberResource($member)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('businesses.members.show', ['persona' => $member->id]));
    }

    /** Show one person of the staff of the business of the session. */
    public function show(string $persona, BusinessContext $businessContext, GetMember $getMember): MemberResource
    {
        return new MemberResource($getMember->execute($businessContext->current()->value, $persona));
    }

    /** Change the name, the role, whether the person attends appointments and whether they stay active. */
    public function update(
        UpdateMemberRequest $request,
        string $persona,
        BusinessContext $businessContext,
        UpdateMember $updateMember,
    ): MemberResource {
        return new MemberResource($updateMember->execute($request->toCommand($businessContext, $persona)));
    }
}
