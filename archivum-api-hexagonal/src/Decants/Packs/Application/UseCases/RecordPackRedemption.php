<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\UseCases;

use Src\Decants\Packs\Application\DTOs\RecordPackRedemptionCommand;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;

final readonly class RecordPackRedemption
{
    /** Receive the packs of the business of the operation. */
    public function __construct(private DecantPackRepository $packs) {}

    /**
     * Keep in decant_pack_redemptions the line of an order that took a decant of the pack (R34), once that line is
     * saved, in the transaction of the order that took the decant; a line redeems one decant only.
     */
    public function execute(RecordPackRedemptionCommand $command): void
    {
        $this->packs->addRedemption(new DecantPackId($command->packId), $command->orderLineId);
    }
}
