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
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->integer('duration_minutes');
            $table->integer('price');
            $table->integer('buffer_minutes')->default(0);
            $table->integer('max_perfumes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_minutes_check CHECK (duration_minutes > 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_buffer_minutes_check CHECK (buffer_minutes >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_max_perfumes_check CHECK (max_perfumes >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
