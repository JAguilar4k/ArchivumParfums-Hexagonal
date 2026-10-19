<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Application\Contracts\OwnerAccounts;
use Src\Businesses\Profile\Application\DTOs\RegisterBusinessCommand;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RegisterBusiness
{
    /** Receive the business repository, owner accounts and transaction ports. */
    public function __construct(
        private BusinessRepository $businesses,
        private OwnerAccounts $owners,
        private TransactionRunner $transaction,
    ) {}

    /** Register a business with the default settings and its owner together, so neither exists without the other. */
    public function execute(RegisterBusinessCommand $command): Business
    {
        $name = new BusinessName($command->name);
        $slug = new BusinessSlug($command->slug);
        $timezone = new BusinessTimezone($command->timezone);
        $appointmentPlace = $command->appointmentPlace === null ? null : new AppointmentPlace($command->appointmentPlace);

        return $this->transaction->run(function () use ($command, $name, $slug, $timezone, $appointmentPlace): Business {
            $business = Business::create($this->businesses->nextId(), $name, $slug, $timezone, $appointmentPlace);

            $this->businesses->save($business);
            $this->owners->registerOwner(
                $business->id,
                $command->ownerName,
                $command->ownerEmail,
                $command->ownerPassword,
                $command->ownerAttendsAppointments,
            );

            return $business;
        });
    }
}
