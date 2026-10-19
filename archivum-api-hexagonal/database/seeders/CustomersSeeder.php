<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoAccounts;
use Illuminate\Database\Seeder;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class CustomersSeeder extends Seeder
{
    use SeedsDemoAccounts;

    /**
     * Fictitious customers of each demo business: email, name and test phone. One account is a customer of both.
     *
     * @var array<string, list<array{string, string, string}>>
     */
    private const array CUSTOMERS = [
        BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => [
            ['mariana@clientes.test', 'Mariana Jiménez Soto', '+50688880001'],
            ['carlos@clientes.test', 'Carlos Herrera Mena', '+50688880002'],
            ['sofia@clientes.test', 'Sofía Araya Brenes', '+50688880003'],
        ],
        BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => [
            ['lucia@clientes.test', 'Lucía Brenes Castro', '+50688880004'],
            ['sofia@clientes.test', 'Sofía Araya Brenes', '+50688880003'],
        ],
    ];

    /** Seed the accounts and the customer records of each demo business, in a transaction scoped to it. */
    public function run(BusinessTransaction $transaction): void
    {
        $passwordHash = $this->demoPasswordHash();

        foreach (self::CUSTOMERS as $businessId => $customers) {
            $transaction->run($businessId, function () use ($businessId, $customers, $passwordHash): void {
                foreach ($customers as [$email, $name, $phone]) {
                    CustomerModel::query()->updateOrCreate(
                        ['business_id' => $businessId, 'user_id' => $this->demoAccount($email, $name, $passwordHash)],
                        ['phone' => $phone],
                    );
                }
            });
        }
    }
}
