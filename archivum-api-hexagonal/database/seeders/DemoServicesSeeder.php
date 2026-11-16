<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\SeededBusinessContext;
use Illuminate\Database\Seeder;
use Src\Scheduling\Services\Application\DTOs\CreateServiceCommand;
use Src\Scheduling\Services\Application\UseCases\CreateService;
use Src\Scheduling\Services\Infrastructure\Persistence\EloquentServiceRepository;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class DemoServicesSeeder extends Seeder
{
    /**
     * The service each demo business offers: the one R11 assumes (45 minutes, free, 15 minutes of margin and up to
     * five perfumes). The price being zero is the team's decision for the first delivery.
     *
     * @var array<string, array{string, int, int, int, int}>
     */
    private const array SERVICES = [
        BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => ['Prueba de olores', 45, 0, 15, 5],
        BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => ['Prueba de olores', 45, 0, 15, 5],
    ];

    /**
     * Fill the services of each demo business inside the transaction of that business and with the application role,
     * so the row-level policy of services accepts every row; repeating the seed keeps the existing services.
     */
    public function run(): void
    {
        foreach (self::SERVICES as $businessId => [$name, $duration, $price, $buffer, $maxPerfumes]) {
            new BusinessTransaction()->run(
                $businessId,
                function () use ($businessId, $name, $duration, $price, $buffer, $maxPerfumes): void {
                    if (ServiceModel::query()->where('business_id', $businessId)->where('name', $name)->exists()) {
                        return;
                    }

                    $context = new SeededBusinessContext(new BusinessId($businessId));
                    $createService = new CreateService(
                        new EloquentServiceRepository($context),
                        $context,
                        new DatabaseTransactionRunner,
                    );

                    $createService->execute(new CreateServiceCommand(
                        $name,
                        $duration,
                        new Colones($price),
                        $buffer,
                        $maxPerfumes,
                    ));
                },
            );
        }
    }
}
