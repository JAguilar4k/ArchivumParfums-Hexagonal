<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\RecordPackRedemptionCommand;
use Src\Decants\Packs\Application\UseCases\RecordPackRedemption;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Decants\InMemoryDecantPackRepository;

it('R34: keeps the pack each saved line of an order redeemed its decant from, in the business of the operation', function () {
    $context = new FixedBusinessContext(new BusinessId('0192f0c4-9999-7000-9000-000000000001'));
    $packs = new InMemoryDecantPackRepository($context);
    $record = new RecordPackRedemption($packs);

    $record->execute(new RecordPackRedemptionCommand('0192f0c4-9999-7000-9000-000000000201', '0192f0c4-9999-7000-9000-000000000501'));
    $record->execute(new RecordPackRedemptionCommand('0192f0c4-9999-7000-9000-000000000201', '0192f0c4-9999-7000-9000-000000000502'));

    expect($packs->redemptions)->toBe([
        '0192f0c4-9999-7000-9000-000000000001:0192f0c4-9999-7000-9000-000000000501' => '0192f0c4-9999-7000-9000-000000000201',
        '0192f0c4-9999-7000-9000-000000000001:0192f0c4-9999-7000-9000-000000000502' => '0192f0c4-9999-7000-9000-000000000201',
    ]);
});
