<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the opening hours of each business, one row per tramo of a weekday.
     * The exclusion constraint makes two overlapping tramos of the same day impossible; the row-level policy of the
     * table lives in its own migration, as in the catalog.
     */
    public function up(): void
    {
        // A GiST index compares scalars with "=" only through btree_gist; the appointments of #59 reuse the extension.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Local hours of a tramo: a half-open range, so two contiguous tramos never overlap.
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                      FROM pg_type t
                      JOIN pg_namespace n ON n.oid = t.typnamespace
                     WHERE t.typname = 'timerange' AND n.nspname = 'public'
                ) THEN
                    CREATE TYPE public.timerange AS RANGE (subtype = time);
                END IF;
            END
            $$;
            SQL);

        Schema::create('business_hours', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->integer('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestampsTz();

            // The availability of #61 reads the tramos of one weekday.
            $table->index(['business_id', 'weekday']);
        });

        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_opens_at_check CHECK (closes_at > opens_at)');

        DB::statement(
            'ALTER TABLE business_hours ADD CONSTRAINT business_hours_no_overlap_excl
             EXCLUDE USING gist (business_id WITH =, weekday WITH =, timerange(opens_at, closes_at) WITH &&)'
        );
    }

    /** Drop the opening hours, its exclusion constraint and the range type this migration owns. */
    public function down(): void
    {
        Schema::dropIfExists('business_hours');
        DB::statement('DROP TYPE IF EXISTS public.timerange');
    }
};
