<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /** Seed the application's database with fictitious data. */
    public function run(): void
    {
        $this->call([DemoBusinessesSeeder::class]);
    }
}
