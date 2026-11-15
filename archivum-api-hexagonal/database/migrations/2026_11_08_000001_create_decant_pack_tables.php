<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The offers of decant packs, the packs customers buy and their redemptions (#143). The decants left of a pack
     * never leave the range between 0 and what it bought, and every reference stays in the business of its row.
     */
    public function up(): void
    {
        Schema::create('decant_pack_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->integer('decants_count');
            $table->integer('ml_per_decant');
            $table->integer('price');
            $table->integer('validity_days');
            $table->integer('max_price_per_ml')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'is_active']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE decant_pack_plans
                ADD CONSTRAINT decant_pack_plans_name_check CHECK (length(btrim(name)) > 0),
                ADD CONSTRAINT decant_pack_plans_decants_count_check CHECK (decants_count > 0),
                ADD CONSTRAINT decant_pack_plans_ml_per_decant_check CHECK (ml_per_decant > 0),
                ADD CONSTRAINT decant_pack_plans_price_check CHECK (price > 0),
                ADD CONSTRAINT decant_pack_plans_validity_days_check CHECK (validity_days > 0),
                ADD CONSTRAINT decant_pack_plans_max_price_per_ml_check CHECK (max_price_per_ml > 0);
            SQL);

        Schema::create('decant_packs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->uuid('customer_id');
            $table->uuid('decant_pack_plan_id');
            $table->integer('total_decants');
            $table->integer('remaining_decants');
            $table->integer('ml_per_decant');
            $table->integer('max_price_per_ml')->nullable();
            $table->string('status', 20)->default('pending_payment');
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->foreign(['customer_id', 'business_id'])->references(['id', 'business_id'])->on('customers')->restrictOnDelete();
            $table->foreign(['decant_pack_plan_id', 'business_id'])->references(['id', 'business_id'])->on('decant_pack_plans')->restrictOnDelete();
            $table->index(['business_id', 'customer_id', 'status']);
            $table->index(['business_id', 'status', 'expires_at']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE decant_packs
                ADD CONSTRAINT decant_packs_total_decants_check CHECK (total_decants > 0),
                ADD CONSTRAINT decant_packs_remaining_decants_check CHECK (remaining_decants >= 0 AND remaining_decants <= total_decants),
                ADD CONSTRAINT decant_packs_ml_per_decant_check CHECK (ml_per_decant > 0),
                ADD CONSTRAINT decant_packs_max_price_per_ml_check CHECK (max_price_per_ml > 0),
                ADD CONSTRAINT decant_packs_status_check CHECK (
                    (status = 'pending_payment' AND remaining_decants = total_decants)
                    OR (status = 'active' AND remaining_decants > 0)
                    OR (status = 'exhausted' AND remaining_decants = 0)
                    OR status = 'expired'
                );
            SQL);

        Schema::create('decant_pack_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->uuid('decant_pack_id');
            $table->uuid('order_line_id');
            $table->timestampsTz();
            $table->unique(['business_id', 'order_line_id']);
            $table->foreign(['decant_pack_id', 'business_id'])->references(['id', 'business_id'])->on('decant_packs')->restrictOnDelete();
            $table->foreign(['order_line_id', 'business_id'])->references(['id', 'business_id'])->on('order_lines')->restrictOnDelete();
            $table->index(['business_id', 'decant_pack_id']);
        });

        Schema::table('order_lines', function (Blueprint $table): void {
            $table->foreign(['decant_pack_id', 'business_id'])->references(['id', 'business_id'])->on('decant_packs')->restrictOnDelete();
        });

        $scope = "business_id = NULLIF(current_setting('app.business_id', true), '')::uuid";
        foreach (['decant_pack_plans', 'decant_packs', 'decant_pack_redemptions'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_business_isolation ON {$table} USING ({$scope}) WITH CHECK ({$scope})");
        }
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table): void {
            $table->dropForeign(['decant_pack_id', 'business_id']);
        });
        Schema::dropIfExists('decant_pack_redemptions');
        Schema::dropIfExists('decant_packs');
        Schema::dropIfExists('decant_pack_plans');
    }
};
