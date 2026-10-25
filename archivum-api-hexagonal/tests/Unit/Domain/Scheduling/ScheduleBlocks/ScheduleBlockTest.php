<?php

declare(strict_types=1);

use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Domain\ValueObjects\ScheduleBlockId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * @return array<int, mixed>
 */
function createBlockScenario(): array
{
    return [
        new ScheduleBlockId('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70'),
        new BusinessId('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71'),
        new MemberId('019a1f2e-7c3b-7d4e-9a12-2b3c4d5e6f72'),
        new DateTimeImmutable('2026-10-25 12:00:00'),
        new DateTimeImmutable('2026-10-25 13:00:00'),
        'Almuerzo',
        BlockOrigin::MANUAL,
    ];
}

test('rechaza un bloqueo donde el fin es anterior o igual al inicio', function () {
    $args = createBlockScenario();
    $args[4] = new DateTimeImmutable('2026-10-25 11:00:00'); // Fin antes que inicio

    expect(fn () => ScheduleBlock::create(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.schedule_block.invalid_order');

    $args[4] = new DateTimeImmutable('2026-10-25 12:00:00'); // Fin igual al inicio
    expect(fn () => ScheduleBlock::create(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.schedule_block.invalid_order');
});

test('crea un bloqueo válido para un miembro específico', function () {
    $block = ScheduleBlock::create(...createBlockScenario());

    expect($block)->toBeInstanceOf(ScheduleBlock::class);
});
