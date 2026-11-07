<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Sales\Orders\Application\Contracts\OrderProducts;
use Src\Sales\Orders\Application\Contracts\OrderSearch;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Infrastructure\Accounts\AccountsOrderCustomers;
use Src\Sales\Orders\Infrastructure\Catalog\CatalogOrderProducts;
use Src\Sales\Orders\Infrastructure\Decants\BottleDecantStock;
use Src\Sales\Orders\Infrastructure\Persistence\Repositories\EloquentOrderRepository;
use Src\Sales\Orders\Infrastructure\Policies\OrderPolicy;

final class OrdersServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> */
    public array $bindings = [
        OrderRepository::class => EloquentOrderRepository::class,
        OrderSearch::class => EloquentOrderRepository::class,
        OrderCustomers::class => AccountsOrderCustomers::class,
        OrderProducts::class => CatalogOrderProducts::class,
        DecantStock::class => BottleDecantStock::class,
    ];

    /** Let OrderPolicy decide who reaches an order (#104). */
    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
