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
        // Skip creation if table already exists (created by earlier migration).
        if (Schema::hasTable('business_hours')) {
            return;
        }

        DB::statement(
            "DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'timerange') THEN
                    CREATE TYPE timerange AS RANGE (subtype = time);
                END IF;
            END $$;"
        );
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_time_check CHECK (closes_at > opens_at)');

        DB::statement(
            "ALTER TABLE business_hours 
            ADD CONSTRAINT business_hours_no_overlap_excl 
            EXCLUDE USING gist (
                business_id WITH =,
                weekday WITH =,
                timerange(opens_at, closes_at) WITH &&
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
        DB::statement('DROP TYPE IF EXISTS timerange CASCADE');
    }
};
