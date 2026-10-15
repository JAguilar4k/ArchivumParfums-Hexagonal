<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Application\DTOs\UpdateBusinessProfileCommand;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class UpdateBusinessProfile
{
    /** Receive the business repository port. */
    public function __construct(private BusinessRepository $businesses) {}

    /** Apply the requested changes to the profile and the settings of a business. */
    public function execute(UpdateBusinessProfileCommand $command): Business
    {
        $business = $this->businesses->find(new BusinessId($command->businessId))
            ?? throw BusinessNotFoundException::create();

        $changes = $command->changes;

        $business->revise(
            isset($changes['name']) ? new BusinessName($changes['name']) : $business->name,
            isset($changes['timezone']) ? new BusinessTimezone($changes['timezone']) : $business->timezone,
            isset($changes['appointment_place'])
                ? new AppointmentPlace($changes['appointment_place'])
                : $business->appointmentPlace,
            isset($changes['settings']) ? $business->settings->with($changes['settings']) : $business->settings,
        );

        $this->businesses->save($business);

        return $business;
    }
}
