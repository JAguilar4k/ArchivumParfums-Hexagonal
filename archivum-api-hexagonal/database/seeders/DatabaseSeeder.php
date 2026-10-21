<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    /** Seed the fictitious demo data in one transaction, so a refused seed leaves nothing behind. */
    public function run(): void
    {
        $this->call([
        BusinessesSeeder::class,
        StaffSeeder::class,
        CustomersSeeder::class,
        CatalogDemoSeeder::class,
        //DemoServicesSeeder::class, /
        ]);
    }
}
