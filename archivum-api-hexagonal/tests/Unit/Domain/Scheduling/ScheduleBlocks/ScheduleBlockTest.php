<?php

declare(strict_types=1);

use Src\Scheduling\ScheduleBlocks\Domain\Enums\BlockOrigin;
use Src\Scheduling\ScheduleBlocks\Domain\ScheduleBlock;

test('rechaza un bloqueo donde el fin es anterior o igual al inicio', function () {
    $inicio = new DateTimeImmutable('2026-10-25 14:00:00');
    $fin = new DateTimeImmutable('2026-10-25 13:00:00'); // Fin es antes

    expect(fn () => ScheduleBlock::create('block-1', 'member-1', $inicio, $fin, 'Almuerzo', BlockOrigin::MANUAL))
        ->toThrow(Exception::class, 'scheduling.schedule_block.invalid_order');

    $mismoTiempo = new DateTimeImmutable('2026-10-25 14:00:00');
    expect(fn () => ScheduleBlock::create('block-2', null, $inicio, $mismoTiempo, 'Fumigación', BlockOrigin::MANUAL))
        ->toThrow(Exception::class, 'scheduling.schedule_block.invalid_order');
});

test('crea un bloqueo válido para un miembro específico', function () {
    $inicio = new DateTimeImmutable('2026-10-25 12:00:00');
    $fin = new DateTimeImmutable('2026-10-25 13:00:00');

    $block = ScheduleBlock::create('block-1', 'member-1', $inicio, $fin, 'Almuerzo', BlockOrigin::MANUAL);

    expect($block)->toBeInstanceOf(ScheduleBlock::class);
});
