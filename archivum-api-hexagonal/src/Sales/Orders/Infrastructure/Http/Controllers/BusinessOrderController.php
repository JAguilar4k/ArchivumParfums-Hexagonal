<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Sales\Orders\Application\UseCases\DeliverOrder;
use Src\Sales\Orders\Application\UseCases\GetBusinessOrder;
use Src\Sales\Orders\Application\UseCases\ListBusinessOrders;
use Src\Sales\Orders\Application\UseCases\MarkOrderLineUnavailable;
use Src\Sales\Orders\Application\UseCases\MarkOrderReady;
use Src\Sales\Orders\Application\UseCases\PrepareOrderDecants;
use Src\Sales\Orders\Infrastructure\Http\Requests\IndexOrders;
use Src\Sales\Orders\Infrastructure\Http\Resources\OrderResource;

final readonly class BusinessOrderController
{
    public function index(IndexOrders $request, ListBusinessOrders $listOrders): AnonymousResourceCollection
    {
        $orders = $listOrders->execute($request->filled('estado') ? $request->string('estado')->toString() : null);
        $page = $request->integer('pagina', 1);
        $perPage = $request->integer('por_pagina', 15);

        return OrderResource::collection(new LengthAwarePaginator(array_slice($orders, ($page - 1) * $perPage, $perPage), count($orders), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina']));
    }

    public function show(string $pedido, GetBusinessOrder $getOrder): OrderResource
    {
        return new OrderResource($getOrder->execute($pedido));
    }

    public function prepare(Request $request, string $pedido, PrepareOrderDecants $prepare): OrderResource
    {
        return new OrderResource($prepare->execute($pedido, (string) $request->user()?->getAuthIdentifier()));
    }

    public function unavailable(string $pedido, string $linea, MarkOrderLineUnavailable $unavailable): OrderResource
    {
        return new OrderResource($unavailable->execute($pedido, $linea, now()->toIso8601String()));
    }

    public function ready(string $pedido, MarkOrderReady $ready): OrderResource
    {
        return new OrderResource($ready->execute($pedido));
    }

    public function deliver(string $pedido, DeliverOrder $deliver): OrderResource
    {
        return new OrderResource($deliver->execute($pedido));
    }
}
