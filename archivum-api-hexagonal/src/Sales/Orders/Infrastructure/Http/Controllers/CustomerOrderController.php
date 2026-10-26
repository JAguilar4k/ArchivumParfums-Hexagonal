<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Sales\Orders\Application\UseCases\CancelMyOrder;
use Src\Sales\Orders\Application\UseCases\GetMyOrder;
use Src\Sales\Orders\Application\UseCases\ListMyOrders;
use Src\Sales\Orders\Application\UseCases\PlaceOrder;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Infrastructure\Http\Requests\IndexOrders;
use Src\Sales\Orders\Infrastructure\Http\Requests\StoreOrder;
use Src\Sales\Orders\Infrastructure\Http\Resources\OrderResource;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class CustomerOrderController
{
    public function store(StoreOrder $request, PlaceOrder $placeOrder): JsonResponse
    {
        $order = $placeOrder->execute($request->toCommand());

        return (new OrderResource($order))->response($request)->setStatusCode(201)
            ->header('Location', route('sales.customer.orders.show', ['pedido' => $order->id->value]));
    }

    /** Read each business under its own RLS scope, then merge the customer's own records. */
    public function index(IndexOrders $request, CustomerBusinessContext $context): AnonymousResourceCollection
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        app()->instance(BusinessContext::class, $context);
        $parts = $context->eachBusiness(fn (): array => app(ListMyOrders::class)->execute($userId));
        $orders = array_merge(...$parts);
        usort($orders, fn (Order $left, Order $right): int => [$right->createdAt, $right->id->value] <=> [$left->createdAt, $left->id->value]);
        $page = $request->integer('pagina', 1);
        $perPage = $request->integer('por_pagina', 15);

        return OrderResource::collection(new LengthAwarePaginator(array_slice($orders, ($page - 1) * $perPage, $perPage), count($orders), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina']));
    }

    public function show(Request $request, string $pedido, GetMyOrder $getOrder): OrderResource
    {
        return new OrderResource($getOrder->execute($pedido, (string) $request->user()?->getAuthIdentifier()));
    }

    public function cancel(Request $request, string $pedido, CancelMyOrder $cancel): OrderResource
    {
        return new OrderResource($cancel->execute($pedido, (string) $request->user()?->getAuthIdentifier(), now()->toIso8601String()));
    }
}
