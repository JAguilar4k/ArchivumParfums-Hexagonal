<?php

declare(strict_types=1);

use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Application\DTOs\SoldPackPage;
use Src\Decants\Packs\Application\DTOs\SoldPackView;
use Src\Decants\Packs\Application\UseCases\ListMyDecantPacks;
use Src\Decants\Packs\Application\UseCases\ListSoldDecantPacks;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Tests\Support\Decants\FakeDecantPackReader;

const PACK_LISTING_AT = '2026-11-20T10:00:00-06:00';

it('R35: lists the packs of the account as the reader reads them at the moment of the request', function () {
    $bought = [new PurchasedPackView('0192f0c4-0000-7000-9000-000000000001', '0192f0c4-eeee-7000-9000-000000000001', 'Perfumería Primera',
        'perfumeria-primera', '0192f0c4-1111-7000-9000-000000000001', 'Descubrimiento', 5, 3, 5, 'expired', '2026-11-19T10:00:00-06:00', '2026-05-23T10:00:00-06:00')];
    $reader = new FakeDecantPackReader($bought);

    expect(new ListMyDecantPacks($reader)->execute('0192f0c4-aaaa-7000-9000-000000000001', PACK_LISTING_AT))->toBe($bought)
        ->and($reader->accounts)->toEqual([['0192f0c4-aaaa-7000-9000-000000000001', new DateTimeImmutable(PACK_LISTING_AT)]]);
});

it('lists the page of sold packs the reader reads with the status the word of the API names', function (?string $word, ?DecantPackStatus $status) {
    $page = new SoldPackPage([new SoldPackView('0192f0c4-0000-7000-9000-000000000001', 'Mariana Rojas', '0192f0c4-1111-7000-9000-000000000001',
        'Descubrimiento', 5, 0, 5, 'exhausted', '2027-05-02T10:00:00-06:00')], 41, 3, 20);
    $reader = new FakeDecantPackReader(sold: $page);

    expect(new ListSoldDecantPacks($reader)->execute($word, PACK_LISTING_AT, 3, 20))->toBe($page)
        ->and($reader->pages)->toEqual([[$status, new DateTimeImmutable(PACK_LISTING_AT), 3, 20]]);
})->with([
    'every status' => [null, null],
    'pendiente_de_pago' => ['pendiente_de_pago', DecantPackStatus::PendingPayment],
    'activo' => ['activo', DecantPackStatus::Active],
    'agotado' => ['agotado', DecantPackStatus::Exhausted],
    'vencido' => ['vencido', DecantPackStatus::Expired],
]);

it('reads the first page of 20 sold packs when the request names none', function () {
    $reader = new FakeDecantPackReader;

    new ListSoldDecantPacks($reader)->execute(null, PACK_LISTING_AT);

    expect($reader->pages)->toEqual([[null, new DateTimeImmutable(PACK_LISTING_AT), 1, 20]]);
});
