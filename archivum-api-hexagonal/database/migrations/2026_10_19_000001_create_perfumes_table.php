<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the perfumes of each business's catalog. */
    public function up(): void
    {
        Schema::create('perfumes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('brand', 120);
            $table->string('name', 120);
            $table->string('concentration', 60)->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('family', 60)->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            // Target of the composite foreign keys of the notes, tags and variants.
            $table->unique(['id', 'business_id']);
            // The storefront lists the active perfumes of one business.
            $table->index(['business_id', 'is_active']);
        });
    }

    /** Drop the perfumes table. */
    public function down(): void
    {
        Schema::dropIfExists('perfumes');
    }
};
