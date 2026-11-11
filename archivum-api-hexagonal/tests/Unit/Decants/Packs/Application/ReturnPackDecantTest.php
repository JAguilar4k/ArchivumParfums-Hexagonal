<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\RecordPackRedemptionCommand;
use Src\Decants\Packs\Application\DTOs\ReturnPackDecantCommand;
use Src\Decants\Packs\Application\UseCases\RecordPackRedemption;
use Src\Decants\Packs\Application\UseCases\ReturnPackDecant;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\InMemoryDecantPackPlanRepository;
use Tests\Support\Decants\InMemoryDecantPackRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const RETURN_PACK_BUSINESS = '0192f0c4-bbbb-7000-9000-000000000001';
const RETURN_PACK_LINE = '0192f0c4-bbbb-7000-9000-000000000501';

/**
 * Wire the use case to in-memory adapters that hold a pack of 1 decant of 5 ml, paid at 15:05 of 10/11/2026 and valid
 * for 180 days, whose only decant the line RETURN_PACK_LINE of an order redeemed and recorded.
 *
 * @return array{ReturnPackDecant, DecantPack, FixedBusinessContext, InMemoryDecantPackRepository, RecordingTransactionRunner}
 */
function packReturnWiring(): array
{
    $context = new FixedBusinessContext(new BusinessId(RETURN_PACK_BUSINESS));
    $plans = new InMemoryDecantPackPlanRepository($context);
    $plan = DecantPackPlan::create($plans->nextId(), $context->current(), new PlanName('Prueba'), new DecantCount(1),
        new MlPerDecant(5), new PackPrice(9000), new ValidityDays(180), new MaxPricePerMl(2500));
    $packs = new InMemoryDecantPackRepository($context);
    $pack = DecantPack::create($packs->nextId(), '0192f0c4-bbbb-7000-9000-000000000301', $plan, new DateTimeImmutable('2026-11-10T15:30:00-06:00'));
    $pack->activate(new DateTimeImmutable('2026-11-10T15:05:00-06:00'), new ValidityDays(180));
    $pack->redeem(5, 120000, 100, new DateTimeImmutable('2026-12-01T10:00:00-06:00'));
    $packs->add($pack);
    new RecordPackRedemption($packs)->execute(new RecordPackRedemptionCommand($pack->id->value, RETURN_PACK_LINE));
    $transaction = new RecordingTransactionRunner;

    return [new ReturnPackDecant($packs, $transaction), $pack, $context, $packs, $transaction];
}

it('gives the decant of a cancelled order back to its pack once, under the lock of the pack', function () {
    [$return, $pack, , $packs, $transaction] = packReturnWiring();

    $return->execute(new ReturnPackDecantCommand($pack->id->value, RETURN_PACK_LINE, '2026-12-02T09:00:00-06:00'));

    expect($pack->remainingDecants)->toBe(1)
        ->and($pack->status)->toBe(DecantPackStatus::Active)
        ->and($packs->redemptions)->toBe([])
        ->and($packs->lockedIds)->toBe([$pack->id->value])
        ->and($transaction->committed())->toBe(1);

    $return->execute(new ReturnPackDecantCommand($pack->id->value, RETURN_PACK_LINE, '2026-12-02T09:01:00-06:00'));

    expect($pack->remainingDecants)->toBe(1);
});

it('R35: gives the decant back to an exhausted pack whose validity ended, which stays expired', function () {
    [$return, $pack] = packReturnWiring();

    $return->execute(new ReturnPackDecantCommand($pack->id->value, RETURN_PACK_LINE, '2027-05-09T15:05:00-06:00'));

    expect($pack->remainingDecants)->toBe(1)
        ->and($pack->status)->toBe(DecantPackStatus::Expired);
});

it('gives nothing back for a line without a redemption of that pack, nor to a pack of another business', function (Closure $commandFor) {
    [$return, $pack, $context, $packs] = packReturnWiring();

    $return->execute($commandFor($pack, $context));

    expect($pack->remainingDecants)->toBe(0)
        ->and($pack->status)->toBe(DecantPackStatus::Exhausted)
        ->and($packs->redemptions)->toHaveCount(1);
})->with([
    'another line of an order' => [fn (DecantPack $pack): ReturnPackDecantCommand => new ReturnPackDecantCommand($pack->id->value,
        '0192f0c4-bbbb-7000-9000-000000000502', '2026-12-02T09:00:00-06:00')],
    'a pack that the line did not redeem' => [fn (): ReturnPackDecantCommand => new ReturnPackDecantCommand('0192f0c4-bbbb-7000-9000-000000009999',
        RETURN_PACK_LINE, '2026-12-02T09:00:00-06:00')],
    'a pack of another business' => [function (DecantPack $pack, FixedBusinessContext $context): ReturnPackDecantCommand {
        $context->switchTo(new BusinessId('0192f0c4-bbbb-7000-9000-000000000002'));

        return new ReturnPackDecantCommand($pack->id->value, RETURN_PACK_LINE, '2026-12-02T09:00:00-06:00');
    }],
]);
