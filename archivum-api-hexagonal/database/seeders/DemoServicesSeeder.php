<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\Services\Infrastructure\Models\ServiceModel;

class DemoServicesSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = BusinessModel::all();

        foreach ($businesses as $business) {
            ServiceModel::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'name' => 'Prueba de olores',
                ],
                [
                    'id' => (string) Str::uuid(),
                    'duration' => 45,
                    'price' => 0,
                    'margin' => 15,
                    'max_perfumes' => 5,
                    'is_active' => true,
                ]
            );
        }
    }
}
