<?php

declare(strict_types=1);

use Src\Scheduling\Appointments\Domain\ValueObjects\BookingWindow;
use Src\Shared\Domain\Exceptions\InvalidValueException;

/** The window of a store in Costa Rica with 12 hours of notice and 30 days of horizon, seen at 10:00 of November 17. */
function storeBookingWindow(): BookingWindow
{
    $zone = new DateTimeZone('America/Costa_Rica');

    return BookingWindow::of(new DateTimeImmutable('2026-11-17 10:00:00', $zone), 12, 30, $zone);
}

test('takes a start from the end of the notice until the last minute of the last day of the horizon (R13)', function (): void {
    $window = storeBookingWindow();

    expect(fn () => $window->assertAllows(new DateTimeImmutable('2026-11-17T22:00:00-06:00')))->not->toThrow(InvalidValueException::class)
        ->and(fn () => $window->assertAllows(new DateTimeImmutable('2026-12-17T23:45:00-06:00')))->not->toThrow(InvalidValueException::class);
});

test('refuses a start before the notice of the store and one after the last day of its horizon (R13)', function (): void {
    $window = storeBookingWindow();

    expect(fn () => $window->assertAllows(new DateTimeImmutable('2026-11-17T21:45:00-06:00')))
        ->toThrow(InvalidValueException::class, 'scheduling.booking.outside_window')
        ->and(fn () => $window->assertAllows(new DateTimeImmutable('2026-12-18T00:00:00-06:00')))
        ->toThrow(InvalidValueException::class, 'scheduling.booking.outside_window');
});

test('counts the days in the time zone of the store, whatever offset the start carries', function (): void {
    $window = storeBookingWindow();

    // 05:30 UTC of December 18 is still 23:30 of December 17 in the store, its last day; 06:00 UTC is already the 18th.
    expect($window->dayOf(new DateTimeImmutable('2026-12-18T05:30:00+00:00'))->format('Y-m-d H:i'))->toBe('2026-12-17 00:00')
        ->and(fn () => $window->assertAllows(new DateTimeImmutable('2026-12-18T05:30:00+00:00')))->not->toThrow(InvalidValueException::class)
        ->and(fn () => $window->assertAllows(new DateTimeImmutable('2026-12-18T06:00:00+00:00')))
        ->toThrow(InvalidValueException::class, 'scheduling.booking.outside_window');
});

test('reads the start a request sends and refuses a text that is not an instant', function (): void {
    expect(BookingWindow::startFrom('2026-11-20T09:00:00-06:00')->format(DATE_ATOM))->toBe('2026-11-20T09:00:00-06:00')
        ->and(fn () => BookingWindow::startFrom('el lunes temprano'))
        ->toThrow(InvalidValueException::class, 'scheduling.booking.invalid_start');
});
