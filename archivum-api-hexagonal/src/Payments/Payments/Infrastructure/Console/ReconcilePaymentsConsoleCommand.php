<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Payments\Payments\Application\Contracts\PendingPayments;
use Src\Payments\Payments\Application\Contracts\PendingRefunds;
use Src\Payments\Payments\Application\DTOs\ReconcilePaymentCommand;
use Src\Payments\Payments\Application\DTOs\ReconcileRefundCommand;
use Src\Payments\Payments\Application\UseCases\ReconcilePayment;
use Src\Payments\Payments\Application\UseCases\ReconcileRefund;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Infrastructure\Tenancy\PaymentEventBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\DomainException;

#[Signature('archivum:reconcile-payments {--limit=500}')]
final class ReconcilePaymentsConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('payments.reconcile_payments_description'));
    }

    /**
     * Ask the provider about every pending payment that already waited for its notice, and then about every pending
     * refund it already accepted (#97), each one in a transaction of its own business. One the provider cannot answer
     * for stays pending for the next run, and the others are still reviewed.
     */
    public function handle(PendingPayments $pendingPayments, PendingRefunds $pendingRefunds): int
    {
        $settled = 0;
        $unreviewed = 0;
        $settledRefunds = 0;
        $unreviewedRefunds = 0;

        // A command has no request that names a business: the use cases are resolved only after this context is bound,
        // so every repository they use asks the business that ReconcilePayment and ReconcileRefund pin for each payment
        // or refund. The binding is removed afterwards, as ProcessPaymentEventJob does.
        app()->instance(BusinessContext::class, app(PaymentEventBusinessContext::class));

        try {
            $reconcilePayment = app(ReconcilePayment::class);
            $reconcileRefund = app(ReconcileRefund::class);

            foreach ($pendingPayments->dueAt(now()->toDateTimeImmutable(), (int) $this->option('limit')) as $payment) {
                try {
                    $settled += (int) $reconcilePayment->execute(new ReconcilePaymentCommand($payment->paymentId, $payment->businessId));
                } catch (DomainException $exception) {
                    $unreviewed++;

                    // A provider that does not answer is expected and is asked again in the next run; any other refusal,
                    // such as an amount that is not the payment's, goes to the log for someone to look at.
                    if (! $exception instanceof GatewayUnavailableException) {
                        report($exception);
                    }
                }
            }

            foreach ($pendingRefunds->dueAt(now()->toDateTimeImmutable(), (int) $this->option('limit')) as $refund) {
                try {
                    $settledRefunds += (int) $reconcileRefund->execute(new ReconcileRefundCommand($refund->refundId, $refund->businessId));
                } catch (DomainException $exception) {
                    $unreviewedRefunds++;

                    if (! $exception instanceof GatewayUnavailableException) {
                        report($exception);
                    }
                }
            }
        } finally {
            app()->forgetInstance(BusinessContext::class);
        }

        $this->components->info(trans_choice('payments.payments_reconciled', $settled, ['count' => $settled]));

        if ($unreviewed > 0) {
            $this->components->warn(trans_choice('payments.payments_not_reconciled', $unreviewed, ['count' => $unreviewed]));
        }

        $this->components->info(trans_choice('payments.refunds_reconciled', $settledRefunds, ['count' => $settledRefunds]));

        if ($unreviewedRefunds > 0) {
            $this->components->warn(trans_choice('payments.refunds_not_reconciled', $unreviewedRefunds, ['count' => $unreviewedRefunds]));
        }

        return self::SUCCESS;
    }
}
