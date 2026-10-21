<?php

declare(strict_types=1);

use Src\Scheduling\Services\Domain\Service;

test('crea un servicio válido y lo inicializa como activo', function () {
    $service = Service::create('uuid-1', 'Cata VIP', 60, 50000, 15, 5);
    
    expect($service->isActive())->toBeTrue();
});

test('rechaza un servicio con duración de cero o menos minutos', function () {
    expect(fn() => Service::create('uuid-1', 'Cata VIP', 0, 50000, 15, 5))
        ->toThrow(Exception::class, 'scheduling.service.invalid_duration');

    expect(fn() => Service::create('uuid-1', 'Cata VIP', -10, 50000, 15, 5))
        ->toThrow(Exception::class, 'scheduling.service.invalid_duration');
});

test('rechaza un servicio con precio, margen o máximo de perfumes negativos', function () {
    expect(fn() => Service::create('uuid-1', 'Cata VIP', 60, -1, 15, 5))
        ->toThrow(Exception::class, 'scheduling.service.invalid_price');

    expect(fn() => Service::create('uuid-1', 'Cata VIP', 60, 50000, -5, 5))
        ->toThrow(Exception::class, 'scheduling.service.invalid_margin');

    expect(fn() => Service::create('uuid-1', 'Cata VIP', 60, 50000, 15, -1))
        ->toThrow(Exception::class, 'scheduling.service.invalid_max_perfumes');
});

test('puede desactivar y reactivar un servicio', function () {
    $service = Service::create('uuid-1', 'Cata VIP', 60, 50000, 15, 5);
    
    $service->deactivate();
    expect($service->isActive())->toBeFalse();

    $service->reactivate();
    expect($service->isActive())->toBeTrue();
});