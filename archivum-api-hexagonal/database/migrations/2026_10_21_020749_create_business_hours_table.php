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
        // 1. Habilitamos la extensión necesaria para el índice GiST (nunca se revierte)
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // 2. Creamos el tipo de dato timerange personalizado[cite: 2]
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'timerange') THEN
                    CREATE TYPE timerange AS RANGE (subtype = time);
                END IF;
            END
            $$;
        ");

        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('id')->primary(); // [cite: 2]
            $table->uuid('business_id');
            $table->integer('weekday');
            $table->time('opens_at'); // Tramos guardan horas locales en time[cite: 2]
            $table->time('closes_at'); // [cite: 2]
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // 3. Restricciones CHECK nombradas según la convención[cite: 2]
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_weekday_check CHECK (weekday >= 1 AND weekday <= 7)');
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_closes_at_check CHECK (closes_at > opens_at)');

        // 4. Restricción de exclusión para impedir traslapes exactos en la misma tienda y día[cite: 2]
        DB::statement('
            ALTER TABLE business_hours 
            ADD CONSTRAINT business_hours_overlap_exclude 
            EXCLUDE USING gist (
                business_id WITH =, 
                weekday WITH =, 
                timerange(opens_at, closes_at) WITH &&
            )
        ');

        // 5. Políticas de seguridad de nivel de fila (RLS)[cite: 2]
        DB::statement('ALTER TABLE business_hours ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE business_hours FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_business_hours_tenant ON business_hours
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS isolate_business_hours_tenant ON business_hours');
        Schema::dropIfExists('business_hours');
        // Borramos el tipo, pero la extensión btree_gist no se borra como dice el requerimiento[cite: 2]
        DB::statement('DROP TYPE IF EXISTS timerange');
    }
};
