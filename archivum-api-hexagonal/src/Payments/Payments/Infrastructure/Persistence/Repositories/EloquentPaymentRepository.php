<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Str;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

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

    public function save(Payment $payment): void
    {
        $businessId = $this->business->current()->value;
        if ($payment->businessId->value !== $businessId || $payment->payable->type !== 'order') {
            throw new PaymentNotFoundException('payments.not_found');
        }

        PaymentModel::query()->updateOrCreate(['id' => $payment->id->value, 'business_id' => $businessId], [
            'order_id' => $payment->payable->id,
            'purpose' => $payment->purpose->value,
            'amount' => $payment->amount->amount,
            'gateway' => $payment->gateway,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
            'charged_currency' => $payment->chargedCurrency,
            'charged_amount' => $payment->chargedAmount,
            'exchange_rate' => $payment->exchangeRate,
            'provider_reference' => $payment->providerReference,
            'checkout_reference' => $payment->checkoutReference,
            'checkout_url' => $payment->checkoutUrl,
            'capture_reference' => $payment->captureReference,
            'refunded_amount' => $payment->refundedAmount,
        ]);
    }

    private function restore(PaymentModel $model): Payment
    {
        return Payment::reconstitute(new PaymentId($model->id), new BusinessId($model->business_id), new Payable('order', $model->order_id), PaymentPurpose::from($model->purpose), new Colones($model->amount), $model->gateway, PaymentMethod::from($model->method), $model->exchange_rate, PaymentStatus::from($model->status), $model->provider_reference, $model->checkout_url, $model->checkout_reference, $model->capture_reference, $model->charged_currency, $model->charged_amount, $model->refunded_amount);
    }
}
