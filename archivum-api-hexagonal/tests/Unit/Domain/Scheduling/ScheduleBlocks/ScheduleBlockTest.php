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

test('reconstitutes a stored block without changing its data', function (?MemberId $memberId, BlockOrigin $origin) {
    $block = ScheduleBlock::reconstitute(
        new ScheduleBlockId('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70'),
        new BusinessId('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71'),
        $memberId,
        new DateTimeImmutable('2026-10-25T12:00:00-06:00'),
        new DateTimeImmutable('2026-10-25T19:00:00+00:00'),
        'Bloqueo histórico ficticio',
        $origin,
    );

    expect($block->id->value)->toBe('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70')
        ->and($block->businessId->value)->toBe('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71')
        ->and($block->memberId?->value)->toBe($memberId?->value)
        ->and($block->startsAt->format(DATE_ATOM))->toBe('2026-10-25T12:00:00-06:00')
        ->and($block->endsAt->format(DATE_ATOM))->toBe('2026-10-25T19:00:00+00:00')
        ->and($block->reason)->toBe('Bloqueo histórico ficticio')
        ->and($block->origin)->toBe($origin);
})->with([
    'manual block for a member' => [new MemberId('019a1f2e-7c3b-7d4e-9a12-2b3c4d5e6f72'), BlockOrigin::MANUAL],
    'Google block for the whole business' => [null, BlockOrigin::GOOGLE],
]);

test('rejects a stored block whose end is not after its start', function (DateTimeImmutable $endsAt) {
    $arguments = createBlockScenario();
    $arguments[3] = new DateTimeImmutable('2026-10-25T12:00:00-06:00');
    $arguments[4] = $endsAt;

    expect(fn () => ScheduleBlock::reconstitute(...$arguments))
        ->toThrow(InvalidValueException::class, 'scheduling.schedule_block.invalid_order');
})->with([
    'earlier instant expressed in UTC' => [new DateTimeImmutable('2026-10-25T17:59:00+00:00')],
    'same instant with a different offset' => [new DateTimeImmutable('2026-10-25T18:00:00+00:00')],
]);

test('rejects a stored block with a blank reason', function () {
    $arguments = createBlockScenario();
    $arguments[3] = new DateTimeImmutable('2026-10-25T12:00:00-06:00');
    $arguments[4] = new DateTimeImmutable('2026-10-25T19:00:00+00:00');
    $arguments[5] = '   ';

    expect(fn () => ScheduleBlock::reconstitute(...$arguments))
        ->toThrow(InvalidValueException::class, 'scheduling.block.reason_required');
});
