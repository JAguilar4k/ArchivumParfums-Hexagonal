<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\UseCases;

use Src\Scheduling\Services\Application\DTOs\EditServiceCommand;
use Src\Scheduling\Services\Domain\Contracts\ServiceRepository;
use Src\Scheduling\Services\Domain\Entities\Service;
use Src\Scheduling\Services\Domain\Exceptions\ServiceNotFoundException;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\Contracts\TransactionRunner;

/**
 * Change the fields sent of a service of the business of the session, also to deactivate or reactivate it.
 * A service of another business is refused as not found, so the answer never reveals that it exists.
 */
final readonly class EditService
{
    /** Receive the repository and the transaction port. */
    public function __construct(
        private ServiceRepository $services,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply the changes of the command and store the service.
     *
     * @throws ServiceNotFoundException
     */
    public function execute(EditServiceCommand $command): Service
    {
        return $this->transaction->run(function () use ($command): Service {
            $service = $this->services->findById(new ServiceId($command->serviceId))
                ?? throw ServiceNotFoundException::create();

            $changes = $command->changes;

            if (isset($changes['name'])) {
                $service->rename($changes['name']);
            }

            if (isset($changes['duration_minutes'])) {
                $service->changeDuration($changes['duration_minutes']);
            }

            if (isset($changes['price'])) {
                $service->changePrice($changes['price']);
            }

            if (isset($changes['buffer_minutes'])) {
                $service->changeBuffer($changes['buffer_minutes']);
            }

            if (isset($changes['max_perfumes'])) {
                $service->changeMaxPerfumes($changes['max_perfumes']);
            }

            if (isset($changes['active'])) {
                $changes['active'] ? $service->activate() : $service->deactivate();
            }

            $this->services->save($service);

            return $service;
        });
    }
}
