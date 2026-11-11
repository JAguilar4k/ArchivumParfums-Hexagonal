<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Str;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\ManualPaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\ManualRecord;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class EloquentPaymentRepository implements PaymentRepository
{
    public function __construct(private BusinessContext $business) {}

    public function nextId(): PaymentId
    {
        return new PaymentId((string) Str::uuid7());
    }

    public function find(PaymentId $id, bool $forUpdate = false): ?Payment
    {
        $query = PaymentModel::query()->where('business_id', $this->business->current()->value)->whereKey($id->value);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $model = $query->first();

        return $model === null ? null : $this->restore($model);
    }

    public function pending(Payable $payable, PaymentPurpose $purpose): ?Payment
    {
        $model = PaymentModel::query()->where('business_id', $this->business->current()->value)
            ->where('order_id', $payable->id)->where('purpose', $purpose->value)->where('status', 'pending')->first();

        return $model === null ? null : $this->restore($model);
    }

    public function findByReference(string $gateway, string $reference): ?Payment
    {
        $model = PaymentModel::query()->where('business_id', $this->business->current()->value)->where('gateway', $gateway)
            ->where(fn ($query) => $query->where('provider_reference', $reference)->orWhere('checkout_reference', $reference)->orWhere('capture_reference', $reference))
            ->orderBy('id')->first();

        return $model === null ? null : $this->restore($model);
    }

    public function save(Payment $payment): void
    {
        $businessId = $this->business->current()->value;
        if ($payment->businessId->value !== $businessId || $payment->payable->type !== 'order') {
            throw new PaymentNotFoundException('payments.not_found');
        }

        $manual = $payment->manual;
        PaymentModel::query()->updateOrCreate(['id' => $payment->id->value, 'business_id' => $businessId], [
            'order_id' => $payment->payable->id,
            'purpose' => $payment->purpose->value,
            'amount' => $payment->amount->amount,
            'gateway' => $payment->gateway,
            'method' => $payment->method?->value,
            'status' => $payment->status->value,
            'charged_currency' => $payment->chargedCurrency,
            'charged_amount' => $payment->chargedAmount,
            'exchange_rate' => $payment->exchangeRate,
            'provider_reference' => $payment->providerReference,
            'checkout_reference' => $payment->checkoutReference,
            'checkout_url' => $payment->checkoutUrl,
            'capture_reference' => $payment->captureReference,
            'refunded_amount' => $payment->refundedAmount,
            'recorded_by' => $manual?->recordedBy->value,
            'manual_method' => $manual?->method->value,
            'manual_reference' => $manual?->reference,
            // A payment received on delivery dates from the moment the owner recorded it.
            ...($manual === null ? [] : ['created_at' => $manual->recordedAt]),
        ]);
    }

    public function saveIfPending(Payment $payment): bool
    {
        // UPDATE ... WHERE status = 'pending': the database lets only one operation take the payment out of pending.
        return $this->saveIfStatusIn($payment, ['pending']);
    }

    public function saveIfPendingOrExpired(Payment $payment): bool
    {
        // UPDATE ... WHERE status IN ('pending', 'expired'): only one operation records a late charge (#192).
        return $this->saveIfStatusIn($payment, ['pending', 'expired']);
    }

    /**
     * Save what a settlement changed in the payment, only while its row still has one of these statuses; answer whether
     * it did.
     *
     * @param  list<string>  $statuses
     */
    private function saveIfStatusIn(Payment $payment, array $statuses): bool
    {
        $businessId = $this->business->current()->value;
        if ($payment->businessId->value !== $businessId) {
            throw new PaymentNotFoundException('payments.not_found');
        }

        return PaymentModel::query()->where('business_id', $businessId)->whereKey($payment->id->value)->whereIn('status', $statuses)
            ->update([
                'status' => $payment->status->value,
                'charged_currency' => $payment->chargedCurrency,
                'charged_amount' => $payment->chargedAmount,
                'provider_reference' => $payment->providerReference,
                'checkout_reference' => $payment->checkoutReference,
                'checkout_url' => $payment->checkoutUrl,
                'capture_reference' => $payment->captureReference,
            ]) === 1;
    }

    private function restore(PaymentModel $model): Payment
    {
        $manual = $model->gateway === 'manual'
            ? new ManualRecord(new MemberId((string) $model->recorded_by), ManualPaymentMethod::from((string) $model->manual_method), (string) $model->manual_reference, $model->created_at)
            : null;

        return Payment::reconstitute(new PaymentId($model->id), new BusinessId($model->business_id), new Payable('order', $model->order_id), PaymentPurpose::from($model->purpose), new Colones($model->amount), $model->gateway, $model->method === null ? null : PaymentMethod::from($model->method), $model->exchange_rate, PaymentStatus::from($model->status), $model->provider_reference, $model->checkout_url, $model->checkout_reference, $model->capture_reference, $model->charged_currency, $model->charged_amount, $model->refunded_amount, $manual);
    }
}
