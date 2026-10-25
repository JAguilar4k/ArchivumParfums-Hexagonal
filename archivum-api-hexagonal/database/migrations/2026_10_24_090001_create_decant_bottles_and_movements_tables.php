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
        Schema::table('perfume_variants', function (Blueprint $table): void {
            $table->unique(['id', 'business_id']);
        });

        Schema::create('decant_bottles', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->uuid('perfume_variant_id');
            $table->integer('total_ml');
            $table->integer('remaining_ml');
            $table->integer('reserved_ml')->default(0);
            $table->string('status', 10)->default('open');
            $table->timestampTz('opened_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->foreign(['perfume_variant_id', 'business_id'])->references(['id', 'business_id'])->on('perfume_variants')->restrictOnDelete();
            $table->index(['business_id', 'perfume_variant_id', 'status']);
        });

        DB::statement('ALTER TABLE decant_bottles ADD CONSTRAINT decant_bottles_total_ml_check CHECK (total_ml > 0)');
        DB::statement('ALTER TABLE decant_bottles ADD CONSTRAINT decant_bottles_remaining_ml_check CHECK (remaining_ml >= 0 AND remaining_ml <= total_ml)');
        DB::statement('ALTER TABLE decant_bottles ADD CONSTRAINT decant_bottles_reserved_ml_check CHECK (reserved_ml >= 0 AND reserved_ml <= remaining_ml)');
        DB::statement("ALTER TABLE decant_bottles ADD CONSTRAINT decant_bottles_status_check CHECK ((status = 'open' AND remaining_ml > 0) OR (status = 'empty' AND remaining_ml = 0))");

        Schema::create('decant_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->uuid('decant_bottle_id');
            $table->string('type', 20);
            $table->integer('ml');
            $table->uuid('order_line_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign(['decant_bottle_id', 'business_id'])->references(['id', 'business_id'])->on('decant_bottles')->restrictOnDelete();
            $table->index(['business_id', 'decant_bottle_id', 'created_at']);
        });

        DB::statement("ALTER TABLE decant_movements ADD CONSTRAINT decant_movements_type_check CHECK (type IN ('reservation', 'release', 'consumption', 'adjustment'))");
        DB::statement("ALTER TABLE decant_movements ADD CONSTRAINT decant_movements_ml_check CHECK ((type = 'adjustment' AND ml <> 0) OR (type <> 'adjustment' AND ml > 0))");
        DB::statement("ALTER TABLE decant_movements ADD CONSTRAINT decant_movements_reason_check CHECK (type <> 'adjustment' OR (reason IS NOT NULL AND length(btrim(reason)) > 0))");

        foreach (['decant_bottles', 'decant_movements'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }

        $scope = "business_id = NULLIF(current_setting('app.business_id', true), '')::uuid";
        DB::statement("CREATE POLICY decant_bottles_business_isolation ON decant_bottles USING ({$scope}) WITH CHECK ({$scope})");
        DB::statement("CREATE POLICY decant_movements_business_read ON decant_movements FOR SELECT USING ({$scope})");
        DB::statement("CREATE POLICY decant_movements_business_insert ON decant_movements FOR INSERT WITH CHECK ({$scope})");

        $name = config('database.connections.pgsql.username');
        $role = '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON decant_movements FROM {$role}");
    }

    public function down(): void
    {
        Schema::dropIfExists('decant_movements');
        Schema::dropIfExists('decant_bottles');
        Schema::table('perfume_variants', function (Blueprint $table): void {
            $table->dropUnique(['id', 'business_id']);
        });
    }
};
