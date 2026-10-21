<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the presentations (sizes in ml) in which each perfume is sold. */
    public function up(): void
    {
        Schema::create('perfume_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('perfume_id');
            $table->smallInteger('size_ml');
            // Colones. Null while the presentation has neither a supplier cost nor a manual price (#43).
            $table->integer('sale_price')->nullable();
            // The price the owner fixed by hand; it replaces the calculated one (R1).
            $table->integer('sale_price_override')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign(['perfume_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('perfumes')
                ->cascadeOnDelete();
            $table->unique(['business_id', 'perfume_id', 'size_ml']);
            // The storefront lists the active presentations of one business.
            $table->index(['business_id', 'is_active']);
        });

        // The price range is still open in #36; these only forbid negative amounts, and null passes a CHECK.
        DB::statement('ALTER TABLE perfume_variants ADD CONSTRAINT perfume_variants_size_ml_check CHECK (size_ml > 0)');
        DB::statement('ALTER TABLE perfume_variants ADD CONSTRAINT perfume_variants_sale_price_check CHECK (sale_price >= 0)');
        DB::statement('ALTER TABLE perfume_variants ADD CONSTRAINT perfume_variants_sale_price_override_check CHECK (sale_price_override >= 0)');
    }

    /** Drop the presentations table. */
    public function down(): void
    {
        Schema::dropIfExists('perfume_variants');
    }
};
