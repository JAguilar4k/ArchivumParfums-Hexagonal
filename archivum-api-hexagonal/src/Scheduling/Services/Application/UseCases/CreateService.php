<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\UseCases;

use Src\Scheduling\Services\Application\DTOs\CreateServiceCommand;
use Src\Scheduling\Services\Domain\Contracts\ServiceRepository;
use Src\Scheduling\Services\Domain\Entities\Service;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

/** Register a service in the business of the session. */
final readonly class CreateService
{
    /** Receive the repository, the business of the session and the transaction ports. */
    public function __construct(
        private ServiceRepository $services,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /** Build the service with the values of the command and store it. */
    public function execute(CreateServiceCommand $command): Service
    {
        return $this->transaction->run(function () use ($command): Service {
            $service = Service::create(
                $this->services->nextId(),
                $this->businessContext->current(),
                $command->name,
                $command->durationMinutes,
                $command->price,
                $command->bufferMinutes,
                $command->maxPerfumes,
            );

            if (! $command->isActive) {
                $service->deactivate();
            }

            $this->services->save($service);

            return $service;
        });
    }
}
