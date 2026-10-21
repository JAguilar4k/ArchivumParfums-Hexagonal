<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\StaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Scheduling\Services\Infrastructure\Models\ServiceModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('archivum.demo_password', 'password');

    $this->seed([
        BusinessesSeeder::class,
        StaffSeeder::class,
    ]);

    $this->owner = UserModel::where('email', 'duena@archivumparfums.test')->firstOrFail();
    $this->businessId = BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID;

    // 🔑 Le indicamos a PostgreSQL cuál tenant está ejecutando la prueba
    DB::statement("SET app.business_id = '{$this->businessId}'");
});

test('crea un servicio y devuelve 201 con encabezado Location', function () {
    $payload = [
        'nombre' => 'Masaje Aromático',
        'duracion_minutos' => 60,
        'precio' => 25000,
        'margen_minutos' => 10,
        'maximo_perfumes' => 3,
    ];

    $response = $this->actingAs($this->owner)
        ->postJson('/api/v1/panel/servicios', $payload);

    $response->assertStatus(201)
        ->assertHeader('Location');

    $this->assertDatabaseHas('services', [
        'business_id' => $this->businessId,
        'name' => 'Masaje Aromático',
    ]);
});

test('responde 422 datos-invalidos al enviar campos incorrectos', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/v1/panel/servicios', []);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'datos-invalidos');
});

test('edita y desactiva un servicio con activo false', function () {
    $service = ServiceModel::query()->create([
        'business_id' => $this->businessId,
        'name' => 'Prueba Desactivación',
        'duration' => 30,
        'price' => 10000,
        'margin' => 10,
        'max_perfumes' => 2,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->patchJson("/api/v1/panel/servicios/{$service->id}", [
            'activo' => false,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'is_active' => false,
    ]);
});

test('retorna 404 no-encontrado si se intenta acceder a un servicio de otra tienda', function () {
    $otherBusinessId = BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID;

    $otherService = ServiceModel::query()->create([
        'business_id' => $otherBusinessId,
        'name' => 'Servicio Otra Tienda',
        'duration' => 45,
        'price' => 15000,
        'margin' => 15,
        'max_perfumes' => 4,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/panel/servicios/{$otherService->id}");

    $response->assertStatus(404);
});

test('un colaborador recibe 403 al intentar gestionar servicios', function () {
    // Busca un usuario asignado a la tienda que tenga rol de colaborador (staff/member)
    $staffUserId = DB::table('members')
        ->where('business_id', $this->businessId)
        ->where('role', '!=', 'owner')
        ->value('user_id');

    // Si el seeder solo crea un colaborador genérico, lo obtenemos o creamos con el rol adecuado
    $staffUser = UserModel::find($staffUserId) ?? UserModel::factory()->create();

    if (! $staffUserId) {
        DB::table('members')->insert([
            'id' => (string) Str::uuid(),
            'business_id' => $this->businessId,
            'user_id' => $staffUser->id,
            'role' => 'staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->actingAs($staffUser)
        ->postJson('/api/v1/panel/servicios', [
            'nombre' => 'Intento Staff',
            'duracion_minutos' => 30,
            'precio' => 10000,
            'margen_minutos' => 5,
            'maximo_perfumes' => 2,
        ]);

    $response->assertStatus(403);
});

test('una petición sin autenticación recibe 401', function () {
    $response = $this->getJson('/api/v1/panel/servicios');

    $response->assertStatus(401);
});
