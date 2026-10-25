<?php

declare(strict_types=1);

use Src\Scheduling\Services\Domain\Entities\Service;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

function validServiceArgs(): array
{
    return [

        new ServiceId('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70'),
        new BusinessId('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71'),
        'Cata VIP',
        60,
        new Colones(50000),
        15,
        5,
    ];
}

test('crea un servicio válido y lo inicializa como activo', function () {
    $service = Service::create(...validServiceArgs());

    expect($service->isActive())->toBeTrue();
});

test('rechaza un servicio con duración de cero o menos minutos', function () {
    $args = validServiceArgs();
    $args[3] = 0;

    expect(fn () => Service::create(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.service.invalid_duration');

    $args[3] = -10;
    expect(fn () => Service::create(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.service.invalid_duration');
});

test('rechaza un margen o máximo de perfumes negativos', function () {
    $args = validServiceArgs();
    $args[5] = -5;

    expect(fn () => Service::create(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.service.invalid_buffer');

    $args = validServiceArgs();
    $args[6] = -1;

    expect(fn () => Service::create(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.service.invalid_max_perfumes');
});

test('rechaza un precio negativo al construir Colones', function () {
    expect(fn () => new Colones(-1))
        ->toThrow(InvalidValueException::class, 'money.colones_negative');
});

test('puede desactivar y reactivar un servicio', function () {
    $service = Service::create(...validServiceArgs());

    $service->deactivate();
    expect($service->isActive())->toBeFalse();

    $service->activate();
    expect($service->isActive())->toBeTrue();
});
