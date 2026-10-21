<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary(); //
            $table->uuid('business_id');
            $table->uuid('member_id');
            $table->integer('weekday');
            $table->time('starts_at'); 
            $table->time('ends_at');
            $table->timestamps();

            // Llave foránea compuesta con business_id para impedir asignar personal de otra tienda
            
            $table->foreign(['member_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('members')
                ->cascadeOnDelete();
        });

        // 1. Restricciones CHECK nombradas <tabla>_<columna>_check[cite: 2]
        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_weekday_check CHECK (weekday >= 1 AND weekday <= 7)');
        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_ends_at_check CHECK (ends_at > starts_at)');

        // 2. Restricción de exclusión para evitar traslapes del mismo miembro en el mismo día[cite: 2]
        DB::statement('
            ALTER TABLE staff_schedules 
            ADD CONSTRAINT staff_schedules_overlap_exclude 
            EXCLUDE USING gist (
                member_id WITH =, 
                weekday WITH =, 
                timerange(starts_at, ends_at) WITH &&
            )
        ');

        // 3. Políticas de seguridad de nivel de fila (RLS)[cite: 2]
        DB::statement('ALTER TABLE staff_schedules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE staff_schedules FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_staff_schedules_tenant ON staff_schedules
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS isolate_staff_schedules_tenant ON staff_schedules');
        Schema::dropIfExists('staff_schedules');
    }
};