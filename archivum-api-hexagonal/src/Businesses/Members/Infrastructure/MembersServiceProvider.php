<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Businesses\Members\Application\Contracts\Memberships;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Infrastructure\Persistence\Queries\PostgresMemberships;
use Src\Businesses\Members\Infrastructure\Persistence\Repositories\EloquentMemberRepository;
use Src\Businesses\Members\Infrastructure\Tenancy\StaffBusinessContext;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToBusiness;

final class MembersServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MemberRepository::class => EloquentMemberRepository::class,
        Memberships::class => PostgresMemberships::class,
    ];

    /** Offer the business of the session to the panel routes. */
    public function register(): void
    {
        $this->app->bind(ScopeRequestToBusiness::contextFor(ScopeRequestToBusiness::STAFF), StaffBusinessContext::class);
    }
}
