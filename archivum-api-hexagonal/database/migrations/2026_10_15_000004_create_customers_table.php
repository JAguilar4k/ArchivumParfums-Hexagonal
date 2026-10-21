<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the customer record an account gets in each business where it operates. */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // Text, because the phone is stored encrypted (#110).
            $table->text('phone')->nullable();
            $table->timestampsTz();

            $table->unique(['business_id', 'user_id']);
            $table->unique(['id', 'business_id']);
        });
    }

    /** Drop the customers table. */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
