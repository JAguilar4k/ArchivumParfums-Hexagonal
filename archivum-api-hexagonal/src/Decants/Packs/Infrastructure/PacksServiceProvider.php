<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Decants\Packs\Application\Contracts\DecantSizes;
use Src\Decants\Packs\Application\Contracts\PackCustomers;
use Src\Decants\Packs\Application\Contracts\PaymentDeadline;
use Src\Decants\Packs\Application\Contracts\PublicPackPlanReader;
use Src\Decants\Packs\Application\UseCases\PurchaseDecantPack;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Infrastructure\Accounts\AccountsPackCustomers;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackPlanRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresPublicPackPlanReader;
use Src\Decants\Packs\Infrastructure\Settings\BusinessSettingsDecantSizes;
use Src\Decants\Packs\Infrastructure\Settings\BusinessSettingsPaymentDeadline;
use Src\Payments\Payments\Infrastructure\Persistence\PaymentTransactionRunner;
use Src\Shared\Domain\Contracts\TransactionRunner;

final class PacksServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DecantPackPlanRepository::class => EloquentDecantPackPlanRepository::class,
        DecantPackRepository::class => EloquentDecantPackRepository::class,
        PublicPackPlanReader::class => PostgresPublicPackPlanReader::class,
        DecantSizes::class => BusinessSettingsDecantSizes::class,
        PackCustomers::class => AccountsPackCustomers::class,
        PaymentDeadline::class => BusinessSettingsPaymentDeadline::class,
    ];

    /**
     * The purchase runs without a transaction around the request, so each of its transactions pins the business of
     * the address itself, like the payment use cases do.
     */
    public function register(): void
    {
        $this->app->when(PurchaseDecantPack::class)->needs(TransactionRunner::class)->give(PaymentTransactionRunner::class);
    }
}
