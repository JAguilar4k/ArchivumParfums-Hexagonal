<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the services a business offers, with the columns of section 7 of the specification.
     * The row-level policy of the table lives in its own migration, as in the catalog.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->integer('duration_minutes');
            $table->integer('price');
            $table->integer('buffer_minutes');
            $table->integer('max_perfumes');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            // Destination of the composite foreign keys of the appointments (#59).
            $table->unique(['id', 'business_id']);
            // The panel lists the services of one business, active and inactive.
            $table->index(['business_id', 'is_active']);
        });

        DB::statement("ALTER TABLE services ADD CONSTRAINT services_name_check CHECK (btrim(name) <> '')");
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_minutes_check CHECK (duration_minutes > 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_buffer_minutes_check CHECK (buffer_minutes >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_max_perfumes_check CHECK (max_perfumes >= 0)');
    }

    /** Drop the services table with its constraints, unique key and indexes. */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
