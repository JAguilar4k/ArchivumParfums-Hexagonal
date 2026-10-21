<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the tags of each perfume: moment of the day, climate and occasion. */
    public function up(): void
    {
        Schema::create('perfume_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('perfume_id');
            $table->string('kind', 20);
            $table->string('value', 60);
            $table->timestampsTz();

            $table->foreign(['perfume_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('perfumes')
                ->cascadeOnDelete();
            $table->unique(['business_id', 'perfume_id', 'kind', 'value']);
        });

        DB::statement("ALTER TABLE perfume_tags ADD CONSTRAINT perfume_tags_kind_check CHECK (kind IN ('time_of_day', 'climate', 'occasion'))");
        // The occasion is free text; the moment and the climate come from a closed list, stored in English.
        DB::statement(<<<'SQL'
            ALTER TABLE perfume_tags ADD CONSTRAINT perfume_tags_value_check CHECK (
                (kind = 'time_of_day' AND value IN ('day', 'afternoon', 'night'))
                OR (kind = 'climate' AND value IN ('warm', 'temperate', 'cold'))
                OR kind = 'occasion'
            )
            SQL);
    }

    /** Drop the tags table. */
    public function down(): void
    {
        Schema::dropIfExists('perfume_tags');
    }
};
