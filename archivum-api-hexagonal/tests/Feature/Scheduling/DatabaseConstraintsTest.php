<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Scheduling\BusinessHours\Infrastructure\Models\BusinessHourModel;

test('postgresql impide guardar tramos traslapados en el mismo dia', function () {
    $businessId = Str::uuid()->toString();

    DB::table('businesses')->insert([
        'id' => $businessId,
        'name' => 'Tienda de Prueba',
        'slug' => 'tienda-prueba-'. strtolower(Str::random(5)),
        'timezone' => 'America/Costa_Rica',
        'settings' => '{}',
    ]);

    // Simulamos el RLS configurando el tenant en la sesión de Postgres
    DB::statement("SET app.business_id = '{$businessId}'");

    // 2. Insertamos un horario inicial válido (10:00 a 14:00)
    BusinessHourModel::create([
        'id' => Str::uuid()->toString(),
        'business_id' => $businessId,
        'weekday' => 1,
        'opens_at' => '10:00:00',
        'closes_at' => '14:00:00',
    ]);

    // 3. Intentamos insertar un horario que se traslapa (12:00 a 16:00) y esperamos que explote
    expect(fn () => BusinessHourModel::create([
        'id' => Str::uuid()->toString(),
        'business_id' => $businessId,
        'weekday' => 1,
        'opens_at' => '12:00:00',
        'closes_at' => '16:00:00',
    ]))->toThrow(QueryException::class, 'business_hours_overlap_exclude');
});
