<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $order_id
 * @property string $purpose
 * @property int $amount
 * @property string $gateway
 * @property string $method
 * @property string $status
 * @property string $charged_currency
 * @property int $charged_amount
 * @property string|null $exchange_rate
 * @property string|null $provider_reference
 * @property string|null $checkout_reference
 * @property string|null $checkout_url
 * @property string|null $capture_reference
 * @property int $refunded_amount
 */
#[Table('payments')]
#[Fillable(['id', 'business_id', 'order_id', 'purpose', 'amount', 'gateway', 'method', 'status', 'charged_currency', 'charged_amount', 'exchange_rate', 'provider_reference', 'checkout_reference', 'checkout_url', 'capture_reference', 'refunded_amount'])]
#[UseFactory(PaymentFactory::class)]
final class PaymentModel extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'charged_amount' => 'integer', 'refunded_amount' => 'integer', 'exchange_rate' => 'decimal:6'];
    }
}
