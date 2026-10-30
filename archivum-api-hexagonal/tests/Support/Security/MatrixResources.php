<?php

declare(strict_types=1);

namespace Tests\Support\Security;

use Database\Seeders\BusinessesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Accounts\DemoAccounts;

/**
 * The demo data of the seeders plus one order of Mariana in ArchivumParfums, with a line and a payment, so every
 * route of the permission matrix (#105) has a real resource of the first business to point at.
 */
final class MatrixResources
{
    public const string BUSINESS = BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID;

    public const string OTHER_BUSINESS = BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID;

    /** Demo accounts of the seeders by the role of the matrix; "otra tienda" is the owner of the second business. */
    public const array ACCOUNTS = [
        'dueno' => 'duena@archivumparfums.test',
        'colaborador' => 'asesor@archivumparfums.test',
        'cliente' => 'mariana@clientes.test',
        'otra tienda' => 'dueno@esenciasdelnorte.test',
        'cliente de otra tienda' => 'lucia@clientes.test',
    ];

    /**
     * Seed the demo data and the order, and answer the value of each route parameter of the matrix.
     *
     * @return array<string, string>
     */
    public static function create(): array
    {
        DemoAccounts::seed();

        return app(BusinessTransaction::class)->run(self::BUSINESS, function (): array {
            $variant = DB::table('perfume_variants')->where('business_id', self::BUSINESS)
                ->where('is_active', true)->whereNotNull('sale_price')->orderBy('id')->first(['id', 'perfume_id']);
            $bottle = DB::table('decant_bottles')->where('business_id', self::BUSINESS)->orderBy('id')->value('id');
            $customer = DB::table('customers')->join('users', 'users.id', '=', 'customers.user_id')
                ->where('customers.business_id', self::BUSINESS)->where('users.email', self::ACCOUNTS['cliente'])->value('customers.id');
            $person = DB::table('members')->join('users', 'users.id', '=', 'members.user_id')
                ->where('members.business_id', self::BUSINESS)->where('users.email', self::ACCOUNTS['colaborador'])->value('members.id');
            assert(is_object($variant) && is_string($bottle) && is_string($customer) && is_string($person));

            $order = OrderModel::factory()->create([
                'business_id' => self::BUSINESS, 'customer_id' => $customer, 'status' => 'confirmed',
                'subtotal' => 15000, 'due_now' => 7500, 'paid' => 7500, 'balance' => 7500,
            ]);
            $line = OrderLineModel::query()->create([
                'id' => (string) Str::uuid7(), 'business_id' => self::BUSINESS, 'order_id' => $order->id, 'kind' => 'bottle',
                'perfume_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 15000, 'status' => 'pending',
            ]);
            $payment = PaymentModel::factory()->create([
                'id' => (string) Str::uuid7(), 'business_id' => self::BUSINESS, 'order_id' => $order->id, 'status' => 'succeeded',
                'gateway' => 'onvo', 'amount' => 7500, 'charged_currency' => 'CRC', 'charged_amount' => 7500,
                'provider_reference' => 'session-matrix', 'checkout_reference' => 'session-matrix', 'capture_reference' => 'intent-matrix',
            ]);

            return [
                'tienda' => 'archivumparfums',
                'perfume' => (string) $variant->perfume_id,
                'presentacion' => (string) $variant->id,
                'botella' => $bottle,
                'pedido' => $order->id,
                'linea' => $line->id,
                'pago' => $payment->id,
                'persona' => $person,
            ];
        });
    }

    /** A presentation of the second business, for the identifiers of another business sent in a body. */
    public static function foreignPresentation(): string
    {
        return (string) app(BusinessTransaction::class)->run(self::OTHER_BUSINESS, fn (): mixed => DB::table('perfume_variants')
            ->where('business_id', self::OTHER_BUSINESS)->where('is_active', true)->whereNotNull('sale_price')->orderBy('id')->value('id'));
    }
}
