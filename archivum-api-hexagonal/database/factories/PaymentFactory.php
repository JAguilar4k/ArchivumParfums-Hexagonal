<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfABusiness;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;

/** @extends Factory<PaymentModel> */
final class PaymentFactory extends Factory
{
    use CreatesRowsOfABusiness;

    protected $model = PaymentModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'order_id' => fn (array $attributes): string => OrderModel::factory()->create(['business_id' => $attributes['business_id']])->id,
            'purpose' => 'deposit', 'amount' => 7500, 'gateway' => 'onvo', 'method' => 'card', 'status' => 'pending',
            'charged_currency' => 'CRC', 'charged_amount' => 7500, 'refunded_amount' => 0,
        ];
    }
}
