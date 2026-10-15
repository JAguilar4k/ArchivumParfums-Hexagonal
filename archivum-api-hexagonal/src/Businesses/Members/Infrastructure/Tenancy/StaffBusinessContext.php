<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Tenancy;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Src\Businesses\Members\Application\UseCases\FindMemberByAccount;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class StaffBusinessContext implements BusinessContext
{
    private ?BusinessId $business = null;

    /** Receive the authentication and the use case that finds the membership of an account. */
    public function __construct(
        private readonly Auth $auth,
        private readonly FindMemberByAccount $findMember,
    ) {}

    /** Answer the business of the authenticated person of the staff; an account without one is forbidden. */
    public function current(): BusinessId
    {
        if ($this->business !== null) {
            return $this->business;
        }

        $userId = $this->auth->guard()->id() ?? throw new AuthenticationException;
        $member = $this->findMember->execute((string) $userId) ?? throw new AccessDeniedHttpException;

        return $this->business = $member->businessId;
    }
}
