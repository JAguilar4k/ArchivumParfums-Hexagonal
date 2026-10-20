<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Application\DTOs\UpdateBusinessProfileCommand;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class UpdateBusinessProfile
{
    /** Receive the business repository and transaction ports. */
    public function __construct(
        private BusinessRepository $businesses,
        private TransactionRunner $transaction,
    ) {}

    /** Apply the requested changes to the profile and the settings of a business. */
    public function execute(UpdateBusinessProfileCommand $command): Business
    {
        return $this->transaction->run(function () use ($command): Business {
            $business = $this->businesses->findForUpdate(new BusinessId($command->businessId))
                ?? throw BusinessNotFoundException::create();

            $changes = $command->changes;

            $business->revise(
                isset($changes['name']) ? new BusinessName($changes['name']) : $business->name,
                isset($changes['timezone']) ? new BusinessTimezone($changes['timezone']) : $business->timezone,
                array_key_exists('appointment_place', $changes)
                    ? $this->appointmentPlace($changes['appointment_place'])
                    : $business->appointmentPlace,
                isset($changes['settings'])
                    ? $this->settings($business->settings, $changes['settings'])
                    : $business->settings,
            );

            $this->businesses->save($business);

            return $business;
        });
    }

    /** Build the optional appointment place; null leaves the business without one. */
    private function appointmentPlace(?string $value): ?AppointmentPlace
    {
        return $value === null ? null : new AppointmentPlace($value);
    }

    /**
     * Combine the stored settings with the ones given; the settings validate the result as a whole.
     *
     * @param array{
     *     bottle_margin_colones?: int,
     *     decant_sizes_ml?: list<int>,
     *     minimum_notice_hours?: int,
     *     booking_horizon_days?: int,
     *     appointment_change_cutoff_hours?: int,
     *     waitlist_hold_hours?: int,
     *     unpaid_expiry_minutes?: int,
     *     reminder_hours?: list<int>,
     *     default_gateway?: string,
     *     usd_exchange_rate?: float|null,
     * } $changes
     */
    private function settings(BusinessSettings $stored, array $changes): BusinessSettings
    {
        return new BusinessSettings(
            bottleMarginColones: $changes['bottle_margin_colones'] ?? $stored->bottleMarginColones,
            decantSizesMl: $changes['decant_sizes_ml'] ?? $stored->decantSizesMl,
            bottleDepositPercent: $stored->bottleDepositPercent,
            decantRoundingColones: $stored->decantRoundingColones,
            trialAppointmentPriceColones: $stored->trialAppointmentPriceColones,
            minimumNoticeHours: $changes['minimum_notice_hours'] ?? $stored->minimumNoticeHours,
            bookingHorizonDays: $changes['booking_horizon_days'] ?? $stored->bookingHorizonDays,
            appointmentChangeCutoffHours: $changes['appointment_change_cutoff_hours'] ?? $stored->appointmentChangeCutoffHours,
            waitlistHoldHours: $changes['waitlist_hold_hours'] ?? $stored->waitlistHoldHours,
            unpaidExpiryMinutes: $changes['unpaid_expiry_minutes'] ?? $stored->unpaidExpiryMinutes,
            reminderHours: $changes['reminder_hours'] ?? $stored->reminderHours,
            defaultGateway: $changes['default_gateway'] ?? $stored->defaultGateway,
            usdExchangeRate: array_key_exists('usd_exchange_rate', $changes)
                ? $changes['usd_exchange_rate']
                : $stored->usdExchangeRate,
        );
    }
}
