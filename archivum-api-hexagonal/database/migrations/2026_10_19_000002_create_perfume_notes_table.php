<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the olfactory notes of each perfume, one row per note and layer. */
    public function up(): void
    {
        Schema::create('perfume_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('perfume_id');
            $table->string('layer', 10);
            $table->string('note', 80);
            $table->timestampsTz();

            $table->foreign(['perfume_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('perfumes')
                ->cascadeOnDelete();
            $table->unique(['business_id', 'perfume_id', 'layer', 'note']);
        });

        DB::statement("ALTER TABLE perfume_notes ADD CONSTRAINT perfume_notes_layer_check CHECK (layer IN ('top', 'heart', 'base'))");
    }

    /** Drop the notes table. */
    public function down(): void
    {
        Schema::dropIfExists('perfume_notes');
    }
};
