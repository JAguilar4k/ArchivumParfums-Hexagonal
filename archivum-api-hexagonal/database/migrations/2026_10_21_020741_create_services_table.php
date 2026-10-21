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
            $table->uuid('business_id');
            $table->string('name');

            $table->integer('duration');
            $table->integer('price');
            $table->integer('margin');
            $table->integer('max_perfumes');
            $table->timestamps();

            $table->unique(['id', 'business_id']);

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration > 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_margin_check CHECK (margin >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_max_perfumes_check CHECK (max_perfumes >= 0)');

        DB::statement('ALTER TABLE services ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE services FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_services_tenant ON services
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS isolate_services_tenant ON services');
        Schema::dropIfExists('services');
    }
};
