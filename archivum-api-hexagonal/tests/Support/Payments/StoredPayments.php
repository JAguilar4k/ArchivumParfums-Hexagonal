<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use Illuminate\Support\Str;
use Src\Payments\Payments\Infrastructure\Gateways\GatewayMoney;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;

final class StoredPayments
{
    public static function create(StaffedBusiness $business, OrderModel $order, string $status = 'pending', string $gateway = 'onvo', int $amount = 7500): PaymentModel
    {
        return app(BusinessTransaction::class)->run($business->business->id, function () use ($business, $order, $status, $gateway, $amount): PaymentModel {
            $id = (string) Str::uuid7();

            return PaymentModel::factory()->create([
                'id' => $id, 'business_id' => $business->business->id, 'order_id' => $order->id,
                'status' => $status, 'gateway' => $gateway, 'amount' => $amount,
                'provider_reference' => 'session-'.$id, 'checkout_reference' => 'session-'.$id,
                'capture_reference' => 'intent-'.$id, 'charged_currency' => $gateway === 'paypal' ? 'USD' : 'CRC',
                'charged_amount' => $gateway === 'paypal' ? GatewayMoney::dollarsFromColones($amount, '500') : $amount,
                'exchange_rate' => $gateway === 'paypal' ? '500.000000' : null,
            ]);
        });
    }
}
