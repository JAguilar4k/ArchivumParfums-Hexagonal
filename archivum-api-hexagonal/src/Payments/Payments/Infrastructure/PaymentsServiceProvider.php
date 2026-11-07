<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\Contracts\PaymentBusinesses;
use Src\Payments\Payments\Application\Contracts\PendingPaymentEvents;
use Src\Payments\Payments\Application\Contracts\PendingPayments;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Application\UseCases\ApplyPaymentEvent;
use Src\Payments\Payments\Application\UseCases\ObservePayment;
use Src\Payments\Payments\Application\UseCases\ProcessOrderRefunds;
use Src\Payments\Payments\Application\UseCases\ReconcilePayment;
use Src\Payments\Payments\Application\UseCases\StartPayment;
use Src\Payments\Payments\Domain\Contracts\ExchangeRates;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Infrastructure\Console\ReconcilePaymentsConsoleCommand;
use Src\Payments\Payments\Infrastructure\Gateways\Onvo\OnvoGateway;
use Src\Payments\Payments\Infrastructure\Gateways\PayPal\PayPalGateway;
use Src\Payments\Payments\Infrastructure\GatewaySelection\BusinessExchangeRates;
use Src\Payments\Payments\Infrastructure\GatewaySelection\ConfiguredPaymentGateways;
use Src\Payments\Payments\Infrastructure\Persistence\PaymentTransactionRunner;
use Src\Payments\Payments\Infrastructure\Persistence\Queries\PostgresPaymentBusinesses;
use Src\Payments\Payments\Infrastructure\Persistence\Queries\PostgresPendingPayments;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\DatabaseRefundRepository;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\DatabaseWebhookEventLog;
use Src\Payments\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Payments\Payments\Infrastructure\Policies\PaymentPolicy;
use Src\Payments\Payments\Infrastructure\Queue\QueuedPendingPaymentEvents;
use Src\Payments\Payments\Infrastructure\Sales\PaymentRefundRequests;
use Src\Payments\Payments\Infrastructure\Sales\SalesPayableReader;
use Src\Payments\Payments\Infrastructure\Tenancy\PaymentEventBusinessContext;
use Src\Sales\Orders\Application\Contracts\RefundRequests;
use Src\Shared\Domain\Contracts\TransactionRunner;

final class PaymentsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PaymentRepository::class => EloquentPaymentRepository::class,
        PaymentGateways::class => ConfiguredPaymentGateways::class,
        ExchangeRates::class => BusinessExchangeRates::class,
        PayableReader::class => SalesPayableReader::class,
        RefundRepository::class => DatabaseRefundRepository::class,
        RefundRequests::class => PaymentRefundRequests::class,
        WebhookEventLog::class => DatabaseWebhookEventLog::class,
        PendingPaymentEvents::class => QueuedPendingPaymentEvents::class,
        PaymentBusinesses::class => PostgresPaymentBusinesses::class,
        PendingPayments::class => PostgresPendingPayments::class,
        BusinessScope::class => PaymentEventBusinessContext::class,
    ];

    public function register(): void
    {
        $this->app->when([StartPayment::class, ObservePayment::class, ProcessOrderRefunds::class, ApplyPaymentEvent::class, ReconcilePayment::class])
            ->needs(TransactionRunner::class)->give(PaymentTransactionRunner::class);
        // One per request, job or command: ProcessPaymentEventJob and ReconcilePaymentsConsoleCommand bind it as the
        // BusinessContext, and ProcessPaymentEvent and ReconcilePayment pin the business of each payment through BusinessScope.
        $this->app->scoped(PaymentEventBusinessContext::class);
        $this->app->bind(OnvoGateway::class, fn (Application $app): OnvoGateway => new OnvoGateway(
            (string) config('payments.onvo.secret_key'),
            (string) config('payments.return_url'),
            (string) config('payments.cancel_url'),
            (string) config('payments.onvo.webhook_secret'),
            (int) config('payments.timeout_seconds'),
            (int) config('payments.connect_timeout_seconds'),
        ));
        $this->app->bind(PayPalGateway::class, fn (Application $app): PayPalGateway => new PayPalGateway(
            (string) config('payments.paypal.client_id'),
            (string) config('payments.paypal.client_secret'),
            (string) config('payments.return_url'),
            (string) config('payments.cancel_url'),
            (string) config('payments.paypal.webhook_id'),
            (string) config('payments.paypal.base_url'),
            (int) config('payments.timeout_seconds'),
            (int) config('payments.connect_timeout_seconds'),
        ));
    }

    /**
     * Let PaymentPolicy decide who reaches a payment (#104). Register the console command of the module and run it
     * every five minutes, never twice at the same time: the reconciliation is the safety net for a notice of the
     * provider that never arrived (#96).
     */
    public function boot(): void
    {
        Gate::policy(Payment::class, PaymentPolicy::class);
        $this->commands([ReconcilePaymentsConsoleCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ReconcilePaymentsConsoleCommand::class)
                ->everyFiveMinutes()
                ->withoutOverlapping(15);
        });
    }
}
