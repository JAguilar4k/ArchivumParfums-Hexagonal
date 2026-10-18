<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('reconstitutes a business with the null appointment place supplied by the database default', function () {
    $id = (string) DB::table('businesses')->insertGetId([
        'name' => 'Tienda sin lugar configurado',
        'slug' => 'sin-lugar',
        'created_at' => '2026-10-18 10:00:00',
        'updated_at' => '2026-10-18 10:00:00',
    ]);

    $business = app(BusinessRepository::class)->find(new BusinessId($id));

    expect($business?->appointmentPlace->value)->toBe('');
    expect(DB::table('businesses')->where('id', $id)->value('location_text'))->toBeNull();
});
