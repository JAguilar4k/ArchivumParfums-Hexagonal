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
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary(); // [cite: 1]
            $table->uuid('business_id'); // [cite: 1]
            // Un bloqueo con member_id nulo cubre a toda la tienda[cite: 1]
            $table->uuid('member_id')->nullable();

            // Los bloqueos guardan instantes en timestamptz[cite: 1]
            $table->timestampTz('starts_at'); // [cite: 1]
            $table->timestampTz('ends_at'); // [cite: 1]

            $table->string('reason');
            // source vale manual por defecto y external_id queda nulo[cite: 1]
            $table->string('source')->default('manual'); // [cite: 1]
            $table->string('external_id')->nullable(); // [cite: 1]

            $table->timestamps();

            // Llaves foráneas[cite: 1]
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            // Llave foránea compuesta que permite nulos para no cruzar personal[cite: 1]
            $table->foreign(['member_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('members')
                ->cascadeOnDelete();
        });

        // 1. Restricciones CHECK nombradas <tabla>_<columna>_check[cite: 1]
        DB::statement('ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_ends_at_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_source_check CHECK (source IN ('manual', 'google'))");

        // 2. Políticas de seguridad de nivel de fila (RLS)[cite: 1]
        DB::statement('ALTER TABLE schedule_blocks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE schedule_blocks FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_schedule_blocks_tenant ON schedule_blocks
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS isolate_schedule_blocks_tenant ON schedule_blocks');
        Schema::dropIfExists('schedule_blocks');
    }
};
