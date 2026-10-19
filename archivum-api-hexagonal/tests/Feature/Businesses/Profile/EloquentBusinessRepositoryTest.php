<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('reconstitutes and saves an absent or legacy empty appointment place as null', function (?string $place) {
    $id = (string) DB::table('businesses')->insertGetId([
        'name' => 'Tienda sin lugar configurado',
        'slug' => 'sin-lugar',
        'location_text' => $place,
        'created_at' => '2026-10-18 10:00:00',
        'updated_at' => '2026-10-18 10:00:00',
    ]);

    $repository = app(BusinessRepository::class);
    $business = $repository->find(new BusinessId($id)) ?? throw new RuntimeException('The fixture must exist.');

    expect($business->appointmentPlace)->toBeNull();
    $repository->save($business);
    expect(DB::table('businesses')->where('id', $id)->value('location_text'))->toBeNull();
})->with(['absent' => null, 'legacy empty text' => '']);
