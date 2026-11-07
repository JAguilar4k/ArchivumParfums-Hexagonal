<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Payments\Payments\Application\Contracts\PendingPayments;
use Src\Payments\Payments\Application\DTOs\ReconcilePaymentCommand;
use Src\Payments\Payments\Application\UseCases\ReconcilePayment;
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
     * Ask the provider about every pending payment that already waited for its notice, each one in a transaction of its
     * own business. A payment the provider cannot answer for stays pending for the next run, and the others are still
     * reviewed.
     */
    public function handle(PendingPayments $pendingPayments): int
    {
        $settled = 0;
        $unreviewed = 0;

        // A command has no request that names a business: the use case is resolved only after this context is bound,
        // so every repository it uses asks the business that ReconcilePayment pins for each payment. The binding is
        // removed afterwards, as ProcessPaymentEventJob does.
        app()->instance(BusinessContext::class, app(PaymentEventBusinessContext::class));

        try {
            $reconcilePayment = app(ReconcilePayment::class);

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
        } finally {
            app()->forgetInstance(BusinessContext::class);
        }

        $this->components->info(trans_choice('payments.payments_reconciled', $settled, ['count' => $settled]));

        if ($unreviewed > 0) {
            $this->components->warn(trans_choice('payments.payments_not_reconciled', $unreviewed, ['count' => $unreviewed]));
        }

        return self::SUCCESS;
    }
}
