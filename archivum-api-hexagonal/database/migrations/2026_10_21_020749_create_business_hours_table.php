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
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

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
            // $table->uuid('id')->primary();
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('business_id');
            $table->integer('weekday');
            $table->time('opens_at'); // Tramos guardan horas locales en time[cite: 2]
            $table->time('closes_at'); // [cite: 2]
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_weekday_check CHECK (weekday >= 1 AND weekday <= 7)');
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_closes_at_check CHECK (closes_at > opens_at)');

        DB::statement('
            ALTER TABLE business_hours 
            ADD CONSTRAINT business_hours_overlap_exclude 
            EXCLUDE USING gist (
                business_id WITH =, 
                weekday WITH =, 
                timerange(opens_at, closes_at) WITH &&
            )
        ');

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
        DB::statement('DROP TYPE IF EXISTS timerange');
    }
};
