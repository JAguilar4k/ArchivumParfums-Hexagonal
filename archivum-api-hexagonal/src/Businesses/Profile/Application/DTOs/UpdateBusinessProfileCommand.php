<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\DTOs;

final readonly class UpdateBusinessProfileCommand
{
    /**
     * Carry the fields to change; absent keys, also inside settings, keep their current value.
     *
     * @param array{
     *     name?: string,
     *     appointment_place?: string|null,
     *     timezone?: string,
     *     settings?: array{
     *         bottle_margin_colones?: int,
     *         decant_sizes_ml?: list<int>,
     *         minimum_notice_hours?: int,
     *         booking_horizon_days?: int,
     *         appointment_change_cutoff_hours?: int,
     *         waitlist_hold_hours?: int,
     *         unpaid_expiry_minutes?: int,
     *         reminder_hours?: list<int>,
     *         default_gateway?: string,
     *         usd_exchange_rate?: float|null,
     *     },
     * } $changes
     */
    public function __construct(
        public string $businessId,
        public array $changes,
    ) {}
}
