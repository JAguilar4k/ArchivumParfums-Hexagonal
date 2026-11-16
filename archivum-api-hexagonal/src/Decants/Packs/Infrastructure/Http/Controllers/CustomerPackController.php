<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Application\UseCases\ListMyDecantPacks;
use Src\Decants\Packs\Infrastructure\Http\Resources\CustomerPack;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class CustomerPackController
{
    /**
     * List the decant packs the authenticated customer bought in every store, newest first, with their decants left and
     * their status at this moment: a pack whose validity ended shows vencido even before the queue expires it.
     *
     * Each store is read under its own row-level scope, like the orders of the account.
     *
     * @throws AuthenticationException
     */
    public function index(Request $request, CustomerBusinessContext $context): AnonymousResourceCollection
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $at = now()->toIso8601String();
        app()->instance(BusinessContext::class, $context);
        $packs = array_merge(...$context->eachBusiness(fn (): array => app(ListMyDecantPacks::class)->execute($userId, $at)));
        usort($packs, fn (PurchasedPackView $left, PurchasedPackView $right): int => [new DateTimeImmutable($right->boughtAt), $right->id] <=> [new DateTimeImmutable($left->boughtAt), $left->id]);

        return CustomerPack::collection($packs);
    }
}
