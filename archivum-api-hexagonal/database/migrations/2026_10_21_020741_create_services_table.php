<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            // UUIDv7 por defecto
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('name');

            // Enteros para duraciones y precios[cite: 2]
            $table->integer('duration');
            $table->integer('price');
            $table->integer('margin');
            $table->integer('max_perfumes');
            $table->timestamps();

            // Llave única (id, business_id) para ser destino de las foráneas compuestas[cite: 2]
            $table->unique(['id', 'business_id']);

            // Foránea clásica hacia la tabla de tiendas (asumiendo que viene del issue #26)
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // 1. Restricciones CHECK nombradas estrictamente <tabla>_<columna>_check[cite: 2]
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration > 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_margin_check CHECK (margin >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_max_perfumes_check CHECK (max_perfumes >= 0)');

        // 2. Row Level Security (RLS) para aislamiento estricto[cite: 2]
        DB::statement('ALTER TABLE services ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE services FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_services_tenant ON services
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        // La migración debe ser reversible[cite: 2]
        DB::statement('DROP POLICY IF EXISTS isolate_services_tenant ON services');
        Schema::dropIfExists('services');
    }
};
